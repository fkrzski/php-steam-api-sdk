<?php

declare(strict_types=1);

// Usage: mutation-scope.php <base> <head>. Prints "all", "none", or the classes the diff needs mutated for `pest --class`.

const NO_EFFECT_ON_MUTANTS = [
    '#^docs/#',
    '#^art/#',
    '#^\.claude/#',
    '#^\.github/(?!workflows/tests\.yml$|scripts/)#',
    '#^[^/]+\.md$#',
    '#^(rector\.php|phpstan\.neon\.dist|\.editorconfig|\.gitattributes|\.gitignore)$#',
];

function git(string ...$arguments): ?string
{
    exec('git '.implode(' ', array_map(escapeshellarg(...), $arguments)).' 2>/dev/null', $output, $exitCode);

    return $exitCode === 0 ? implode("\n", $output) : null;
}

function nextToken(array $tokens, int $index): array|string
{
    do {
        $index++;
    } while (is_array($tokens[$index]) && $tokens[$index][0] === T_WHITESPACE);

    return $tokens[$index];
}

function declaredClass(string $code): ?string
{
    $tokens = token_get_all($code);
    $namespace = '';

    foreach ($tokens as $index => $token) {
        if (! is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAMESPACE) {
            $namespace = nextToken($tokens, $index)[1].'\\';
        }

        $isDeclaration = in_array($token[0], [T_CLASS, T_TRAIT, T_ENUM, T_INTERFACE], true)
            && ! (is_array($tokens[$index - 1]) && $tokens[$index - 1][0] === T_DOUBLE_COLON);
        $name = $isDeclaration ? nextToken($tokens, $index) : null;

        if (is_array($name) && $name[0] === T_STRING) {
            return $namespace.$name[1];
        }
    }

    return null;
}

/**
 * Null when an argument isn't an imported or fully qualified `Name::class`.
 */
function coveredClasses(string $code): ?array
{
    $tokens = array_values(array_filter(
        token_get_all($code),
        fn (array|string $token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
    ));
    $imports = [];
    $classes = [];

    foreach ($tokens as $index => $token) {
        if (is_array($token) && $token[0] === T_USE && is_array($tokens[$index + 1]) && $tokens[$index + 1][0] === T_NAME_QUALIFIED) {
            $class = $tokens[$index + 1][1];
            $alias = is_array($tokens[$index + 2]) && $tokens[$index + 2][0] === T_AS ? $tokens[$index + 3][1] : substr($class, strrpos($class, '\\') + 1);
            $imports[$alias] = $class;
        }

        if (! is_array($token) || $token[0] !== T_STRING || ! in_array($token[1], ['covers', 'mutates'], true) || $tokens[$index + 1] !== '(') {
            continue;
        }

        for ($cursor = $index + 2, $depth = 1; $depth > 0; $cursor++) {
            $argument = $tokens[$cursor];

            if ($argument === '(' || $argument === '[') {
                $depth++;
            } elseif ($argument === ')' || $argument === ']') {
                $depth--;
            } elseif (is_array($argument) && is_array($tokens[$cursor + 2]) && $tokens[$cursor + 2][0] === T_CLASS) {
                $class = match ($argument[0]) {
                    T_STRING => $imports[$argument[1]] ?? null,
                    T_NAME_FULLY_QUALIFIED => ltrim($argument[1], '\\'),
                    default => null,
                };

                if ($class === null) {
                    return null;
                }

                $classes[] = $class;
                $cursor += 2;
            } elseif ($argument !== ',') {
                return null;
            }
        }
    }

    return $classes;
}

/**
 * The classes to mutate, or why everything has to be.
 */
function scope(string $base, string $head): array|string
{
    $changed = git('diff', '--name-only', '--no-renames', $base, $head) ?? throw new RuntimeException("Can't diff $base against $head.");
    $classes = [];

    foreach (array_filter(explode("\n", $changed)) as $path) {
        if (array_any(NO_EFFECT_ON_MUTANTS, fn (string $pattern): bool => preg_match($pattern, $path) === 1)) {
            continue;
        }

        if (preg_match('#^src/.+\.php$#', $path) === 1) {
            $class = declaredClass(git('show', "$head:$path") ?? '');
            $classes = $class === null ? $classes : [...$classes, $class];

            continue;
        }

        if (preg_match('#^tests/.+Test\.php$#', $path) !== 1) {
            return "$path changed";
        }

        // The base side counts too: a class dropped from covers() loses the tests that killed its mutants.
        foreach ([$base, $head] as $revision) {
            $covered = coveredClasses(git('show', "$revision:$path") ?? '');

            if ($covered === null) {
                return "$path covers something other than imported classes";
            }

            $classes = [...$classes, ...$covered];
        }
    }

    return array_values(array_unique($classes));
}

if ($argc !== 3) {
    fwrite(STDERR, "Usage: mutation-scope.php <base> <head>\n");

    exit(2);
}

$scope = scope($argv[1], $argv[2]);

if (is_string($scope)) {
    fwrite(STDERR, "Mutating everything: $scope.\n");
    echo "all\n";
} elseif ($scope === []) {
    fwrite(STDERR, "Nothing to mutate.\n");
    echo "none\n";
} else {
    fwrite(STDERR, 'Mutating '.count($scope)." classes:\n  ".implode("\n  ", $scope)."\n");
    echo implode(',', $scope)."\n";
}
