<?php
/**
 * Utsubot - Japanese.php
 * Date: 04/03/2016
 *
 * Function for converting Japanese kana to roman characters
 */

declare(strict_types = 1);

namespace Utsubot\Japanese;

define("Hiragana", [
    'きゃ', 'きゅ', 'きょ',
    'しゃ', 'しゅ', 'しょ',
    'ちゃ', 'ちゅ', 'ちょ',
    'にゃ', 'にゅ', 'にょ',
    'ひゃ', 'ひゅ', 'ひょ',
    'みゃ', 'みゅ', 'みょ',
    'りゃ', 'りゅ', 'りょ',
    'ぎゃ', 'ぎゅ', 'ぎょ',
    'じゃ', 'じゅ', 'じょ',
    'ぢゃ', 'ぢゅ', 'ぢょ',
    'びゃ', 'びゅ', 'びょ',
    'ぴゃ', 'ぴゅ', 'ぴょ',
    'あ', 'い', 'う', 'え', 'お',
    'か', 'き', 'く', 'け', 'こ',
    'さ', 'し', 'す', 'せ', 'そ',
    'た', 'ち', 'つ', 'て', 'と',
    'な', 'に', 'ぬ', 'ね', 'の',
    'は', 'ひ', 'ふ', 'へ', 'ほ',
    'ま', 'み', 'む', 'め', 'も',
    'や', 'ゆ', 'よ',
    'ら', 'り', 'る', 'れ', 'ろ',
    'わ', 'を', 'ん',
    'が', 'ぎ', 'ぐ', 'げ', 'ご',
    'ざ', 'じ', 'ず', 'ぜ', 'ぞ',
    'だ', 'ぢ', 'づ', 'で', 'ど',
    'ば', 'び', 'ぶ', 'べ', 'ぼ',
    'ぱ', 'ぴ', 'ぷ', 'ぺ', 'ぽ',
    'ぁ', 'ぃ', 'ぅ', 'ぇ', 'ぉ'
]);

define("Katakana", [
    'キャ', 'キュ', 'キョ',
    'シャ', 'シュ', 'ショ',
    'チャ', 'チュ', 'チョ',
    'ニャ', 'ニュ', 'ニョ',
    'ヒャ', 'ヒュ', 'ヒョ',
    'ミャ', 'ミュ', 'ミョ',
    'リャ', 'リュ', 'リョ',
    'ギャ', 'ギュ', 'ギョ',
    'ジャ', 'ジュ', 'ジョ',
    'ヂャ', 'ヂュ', 'ヂョ',
    'ビャ', 'ビュ', 'ビョ',
    'ピャ', 'ピュ', 'ピョ',
    'ア', 'イ', 'ウ', 'エ', 'オ',
    'カ', 'キ', 'ク', 'ケ', 'コ',
    'サ', 'シ', 'ス', 'セ', 'ソ',
    'タ', 'チ', 'ツ', 'テ', 'ト',
    'ナ', 'ニ', 'ヌ', 'ネ', 'ノ',
    'ハ', 'ヒ', 'フ', 'ヘ', 'ホ',
    'マ', 'ミ', 'ム', 'メ', 'モ',
    'ヤ', 'ユ', 'ヨ',
    'ラ', 'リ', 'ル', 'レ', 'ロ',
    'ワ', 'ヲ', 'ン',
    'ガ', 'ギ', 'グ', 'ゲ', 'ゴ',
    'ザ', 'ジ', 'ズ', 'ゼ', 'ゾ',
    'ダ', 'ヂ', 'ヅ', 'デ', 'ド',
    'バ', 'ビ', 'ブ', 'ベ', 'ボ',
    'パ', 'ピ', 'プ', 'ペ', 'ポ',
    'ァ', 'ィ', 'ゥ', 'ェ', 'ォ'
]);

define("Extra_Katakana", [
    'ヴァ', 'ヴィ', 'ヴ', 'ヴェ', 'ヴォ',
    'ウィ', 'ウェ', 'ウォ',
    'ファ', 'フィ', 'フェ', 'フォ',
    'ティ', 'ディ',
    'トゥ', 'ドゥ',
    'チェ',
    'シェ'
]);

define("Romanization", [
    'kya', 'kyu', 'kyo',
    'sha', 'shu', 'sho',
    'cha', 'chu', 'cho',
    'nya', 'nyu', 'nyo',
    'hya', 'hyu', 'hyo',
    'mya', 'myu', 'myo',
    'rya', 'ryu', 'ryo',
    'gya', 'gyu', 'gyo',
    'ja', 'ju', 'jo',
    'ja', 'ju', 'jo',
    'bya', 'byu', 'byo',
    'pya', 'pyu', 'pyo',
    'a', 'i', 'u', 'e', 'o',
    'ka', 'ki', 'ku', 'ke', 'ko',
    'sa', 'shi', 'su', 'se', 'so',
    'ta', 'chi', 'tsu', 'te', 'to',
    'na', 'ni', 'nu', 'ne', 'no',
    'ha', 'hi', 'fu', 'he', 'ho',
    'ma', 'mi', 'mu', 'me', 'mo',
    'ya', 'yu', 'yo',
    'ra', 'ri', 'ru', 're', 'ro',
    'wa', 'wo', 'n',
    'ga', 'gi', 'gu', 'ge', 'go',
    'za', 'ji', 'zu', 'ze', 'zo',
    'da', 'ji', 'zu', 'de', 'do',
    'ba', 'bi', 'bu', 'be', 'bo',
    'pa', 'pi', 'pu', 'pe', 'po',
    'a', 'i', 'u', 'e', 'o'
]);

define("Extra_Katakana_Romanization", [
    'va', 'vi', 'vu', 've', 'vo',
    'wi', 'we', 'wo',
    'fa', 'fi', 'fe', 'fo',
    'ti', 'di',
    'tou', 'dou',
    'che',
    'she'
]);

/**
 * Transform a string of kana into romaji
 *
 * @param string $kana
 * @return string
 */
function romanizeKana(string $kana): string {

    //  Adds ' for n followed by vowel (んあ vs. な)
    $kana = preg_replace('/([\\x{30F3}\\x{3093}])([\\x{30A1}-\\x{30AA}\\x{3041}-\\x{304A}])/u', '$1\'$2', $kana);

    //  Replace extra katakana sounds first with romaji
    $kana = str_replace(Extra_Katakana, Extra_Katakana_Romanization, $kana);

    //  Replace katakana with romaji
    $kana = str_replace(Katakana, Romanization, $kana);

    //  Replace hiragana with romaji
    $kana = str_replace(Hiragana, Romanization, $kana);

    //  Double up long katakana vowels (aー to aa, etc)
    $kana = preg_replace('/([aeiou])\\x{30FC}/u', '$1$1', $kana);

    //  Fill in sokuons (っk to kk, etc)
    $kana = preg_replace('/(?:\\x{30C3}|\\x{3063})([a-z])/u', '$1$1', $kana);

    return $kana;
}
