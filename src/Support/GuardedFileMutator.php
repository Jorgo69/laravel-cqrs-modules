<?php

/**
 * @ Author: Ibra Le Jorgo
 * @ Email: ibralejorgo@gmail.com
 * @ Github: https://github.com/Jorgo69
 * @ Gitlab: https://gitlab.com/Jorgo69
 * @ Create Time: 2026-07-21
 * @ Description: Insertion guidee/idempotente dans un fichier PHP existant (array ou corps de methode).
 */

declare(strict_types=1);

namespace Jorgo69\LaravelCqrsModules\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Insere une ligne dans un fichier PHP existant de facon idempotente, sans
 * jamais deviner : si la forme attendue n'est pas retrouvee, la mutation est
 * abandonnee plutot que de risquer de corrompre le fichier.
 */
final class GuardedFileMutator
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * Cas simple : ajoute une ligne juste avant le `];` final d'un fichier de
     * la forme `return [ ... ];` (ex: bootstrap/providers.php).
     */
    public function insertBeforeArrayClose(string $path, string $uniqueMarker, string $lineToInsert): MutationResult
    {
        if (! $this->files->exists($path)) {
            return MutationResult::AbortedFileMissing;
        }

        $contents = $this->files->get($path);

        if (str_contains($contents, $uniqueMarker)) {
            return MutationResult::AlreadyPresent;
        }

        if (! str_contains($contents, 'return [') || ! str_ends_with(trim($contents), '];')) {
            return MutationResult::AbortedShapeMismatch;
        }

        $updated = Str::replaceLast('];', "    {$lineToInsert}\n];", $contents);
        $this->files->put($path, $updated);

        return MutationResult::Inserted;
    }

    /**
     * Cas delicat : ajoute une ligne dans le corps d'une methode precise
     * (ex: `registerHandlers()`), apres le dernier ancrage trouve. Localise la
     * methode et ses bornes via `token_get_all()` (jamais un comptage brut de
     * caracteres) pour ignorer correctement les accolades presentes dans une
     * string, un commentaire, ou une closure imbriquee.
     *
     * @param  list<string>  $anchorPatterns  regex essayees dans l'ordre, la
     *                                        premiere qui matche au moins une fois est utilisee (dernier match).
     */
    public function insertIntoMethodBody(
        string $path,
        string $methodName,
        string $uniqueMarker,
        string $lineToInsert,
        array $anchorPatterns,
    ): MutationResult {
        if (! $this->files->exists($path)) {
            return MutationResult::AbortedFileMissing;
        }

        $contents = $this->files->get($path);

        if (str_contains($contents, $uniqueMarker)) {
            return MutationResult::AlreadyPresent;
        }

        $bounds = $this->locateMethodBodyBounds($contents, $methodName);
        if ($bounds === null) {
            return MutationResult::AbortedShapeMismatch;
        }

        [$bodyStart, $bodyEnd] = $bounds;
        $bodyText = substr($contents, $bodyStart, $bodyEnd - $bodyStart);

        $insertAtRelativeOffset = $this->findAnchorOffset($bodyText, $anchorPatterns);
        if ($insertAtRelativeOffset === null) {
            return MutationResult::AbortedShapeMismatch;
        }

        $absoluteOffset = $bodyStart + $insertAtRelativeOffset;

        $updated = substr($contents, 0, $absoluteOffset).$lineToInsert.substr($contents, $absoluteOffset);
        $this->files->put($path, $updated);

        return MutationResult::Inserted;
    }

    /**
     * @return array{0: int, 1: int}|null bornes [debut, fin[ du corps (hors accolades)
     */
    private function locateMethodBodyBounds(string $contents, string $methodName): ?array
    {
        $tokens = token_get_all($contents);
        $count = count($tokens);

        $functionTokenIndex = null;
        $matchCount = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            $j = $i + 1;
            while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }

            if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && $tokens[$j][1] === $methodName) {
                $functionTokenIndex = $i;
                $matchCount++;
            }
        }

        // zero ou plusieurs matchs : jamais deviner, on abandonne.
        if ($matchCount !== 1 || $functionTokenIndex === null) {
            return null;
        }

        return $this->bodyBoundsFromFunctionTokenIndex($tokens, $functionTokenIndex);
    }

    /**
     * @param  list<mixed>  $tokens
     * @return array{0: int, 1: int}|null
     */
    private function bodyBoundsFromFunctionTokenIndex(array $tokens, int $functionTokenIndex): ?array
    {
        $count = count($tokens);
        $offset = 0;

        for ($i = 0; $i < $functionTokenIndex; $i++) {
            $offset += strlen($this->tokenText($tokens[$i]));
        }

        // avance jusqu'a la premiere accolade ouvrante : la liste de
        // parametres et le type de retour ne contiennent jamais de '{'.
        $i = $functionTokenIndex;
        $openFound = false;

        while ($i < $count) {
            $text = $this->tokenText($tokens[$i]);
            $offset += strlen($text);

            if ($text === '{') {
                $openFound = true;
                $i++;
                break;
            }

            $i++;
        }

        if (! $openFound) {
            return null;
        }

        $bodyStart = $offset;
        $depth = 1;

        while ($i < $count) {
            $token = $tokens[$i];
            $text = $this->tokenText($token);
            $type = is_array($token) ? $token[0] : null;

            $isOpener = $text === '{' || $type === T_CURLY_OPEN || $type === T_DOLLAR_OPEN_CURLY_BRACES;
            $isCloser = $text === '}';

            if ($isOpener) {
                $depth++;
            } elseif ($isCloser) {
                $depth--;
                if ($depth === 0) {
                    return [$bodyStart, $offset];
                }
            }

            $offset += strlen($text);
            $i++;
        }

        return null;
    }

    /**
     * @param  list<string>  $anchorPatterns
     */
    private function findAnchorOffset(string $bodyText, array $anchorPatterns): ?int
    {
        foreach ($anchorPatterns as $pattern) {
            if (preg_match_all($pattern, $bodyText, $matches, PREG_OFFSET_CAPTURE) > 0) {
                [$matchText, $matchOffset] = end($matches[0]);

                return $matchOffset + strlen($matchText);
            }
        }

        return null;
    }

    private function tokenText(mixed $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }
}
