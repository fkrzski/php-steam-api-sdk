<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Enums;

/**
 * Steam's own language codes for the `l` query parameter, not ISO ones — which is why
 * case names and backing values part ways on Korean, Brazilian Portuguese and the rest.
 */
enum Language: string
{
    case Arabic = 'arabic';
    case Bulgarian = 'bulgarian';
    case ChineseSimplified = 'schinese';
    case ChineseTraditional = 'tchinese';
    case Czech = 'czech';
    case Danish = 'danish';
    case Dutch = 'dutch';
    case English = 'english';
    case Finnish = 'finnish';
    case French = 'french';
    case German = 'german';
    case Greek = 'greek';
    case Hungarian = 'hungarian';
    case Indonesian = 'indonesian';
    case Italian = 'italian';
    case Japanese = 'japanese';
    case Korean = 'koreana';
    case Norwegian = 'norwegian';
    case Polish = 'polish';
    case Portuguese = 'portuguese';
    case PortugueseBrazil = 'brazilian';
    case Romanian = 'romanian';
    case Russian = 'russian';
    case Spanish = 'spanish';
    case SpanishLatinAmerican = 'latam';
    case Swedish = 'swedish';
    case Thai = 'thai';
    case Turkish = 'turkish';
    case Ukrainian = 'ukrainian';
    case Vietnamese = 'vietnamese';
}
