<?php

namespace FalconSeo\Support;

/** Every country (ISO 3166-1 alpha-2), for choosing where searchers are. */
final class Countries
{
    public const ALL = [
        'af' => 'Afghanistan', 'ax' => 'Åland Islands', 'al' => 'Albania', 'dz' => 'Algeria', 'as' => 'American Samoa',
        'ad' => 'Andorra', 'ao' => 'Angola', 'ai' => 'Anguilla', 'aq' => 'Antarctica', 'ag' => 'Antigua and Barbuda',
        'ar' => 'Argentina', 'am' => 'Armenia', 'aw' => 'Aruba', 'au' => 'Australia', 'at' => 'Austria',
        'az' => 'Azerbaijan', 'bs' => 'Bahamas', 'bh' => 'Bahrain', 'bd' => 'Bangladesh', 'bb' => 'Barbados',
        'by' => 'Belarus', 'be' => 'Belgium', 'bz' => 'Belize', 'bj' => 'Benin', 'bm' => 'Bermuda',
        'bt' => 'Bhutan', 'bo' => 'Bolivia', 'bq' => 'Bonaire, Sint Eustatius and Saba', 'ba' => 'Bosnia and Herzegovina', 'bw' => 'Botswana',
        'bv' => 'Bouvet Island', 'br' => 'Brazil', 'io' => 'British Indian Ocean Territory', 'bn' => 'Brunei', 'bg' => 'Bulgaria',
        'bf' => 'Burkina Faso', 'bi' => 'Burundi', 'cv' => 'Cabo Verde', 'kh' => 'Cambodia', 'cm' => 'Cameroon',
        'ca' => 'Canada', 'ky' => 'Cayman Islands', 'cf' => 'Central African Republic', 'td' => 'Chad', 'cl' => 'Chile',
        'cn' => 'China', 'cx' => 'Christmas Island', 'cc' => 'Cocos (Keeling) Islands', 'co' => 'Colombia', 'km' => 'Comoros',
        'cg' => 'Congo', 'cd' => 'Congo (Democratic Republic)', 'ck' => 'Cook Islands', 'cr' => 'Costa Rica', 'ci' => "Côte d'Ivoire",
        'hr' => 'Croatia', 'cu' => 'Cuba', 'cw' => 'Curaçao', 'cy' => 'Cyprus', 'cz' => 'Czechia',
        'dk' => 'Denmark', 'dj' => 'Djibouti', 'dm' => 'Dominica', 'do' => 'Dominican Republic', 'ec' => 'Ecuador',
        'eg' => 'Egypt', 'sv' => 'El Salvador', 'gq' => 'Equatorial Guinea', 'er' => 'Eritrea', 'ee' => 'Estonia',
        'sz' => 'Eswatini', 'et' => 'Ethiopia', 'fk' => 'Falkland Islands', 'fo' => 'Faroe Islands', 'fj' => 'Fiji',
        'fi' => 'Finland', 'fr' => 'France', 'gf' => 'French Guiana', 'pf' => 'French Polynesia', 'tf' => 'French Southern Territories',
        'ga' => 'Gabon', 'gm' => 'Gambia', 'ge' => 'Georgia', 'de' => 'Germany', 'gh' => 'Ghana',
        'gi' => 'Gibraltar', 'gr' => 'Greece', 'gl' => 'Greenland', 'gd' => 'Grenada', 'gp' => 'Guadeloupe',
        'gu' => 'Guam', 'gt' => 'Guatemala', 'gg' => 'Guernsey', 'gn' => 'Guinea', 'gw' => 'Guinea-Bissau',
        'gy' => 'Guyana', 'ht' => 'Haiti', 'hm' => 'Heard Island and McDonald Islands', 'va' => 'Holy See', 'hn' => 'Honduras',
        'hk' => 'Hong Kong', 'hu' => 'Hungary', 'is' => 'Iceland', 'in' => 'India', 'id' => 'Indonesia',
        'ir' => 'Iran', 'iq' => 'Iraq', 'ie' => 'Ireland', 'im' => 'Isle of Man', 'il' => 'Israel',
        'it' => 'Italy', 'jm' => 'Jamaica', 'jp' => 'Japan', 'je' => 'Jersey', 'jo' => 'Jordan',
        'kz' => 'Kazakhstan', 'ke' => 'Kenya', 'ki' => 'Kiribati', 'kp' => 'Korea (North)', 'kr' => 'Korea (South)',
        'kw' => 'Kuwait', 'kg' => 'Kyrgyzstan', 'la' => 'Laos', 'lv' => 'Latvia', 'lb' => 'Lebanon',
        'ls' => 'Lesotho', 'lr' => 'Liberia', 'ly' => 'Libya', 'li' => 'Liechtenstein', 'lt' => 'Lithuania',
        'lu' => 'Luxembourg', 'mo' => 'Macao', 'mg' => 'Madagascar', 'mw' => 'Malawi', 'my' => 'Malaysia',
        'mv' => 'Maldives', 'ml' => 'Mali', 'mt' => 'Malta', 'mh' => 'Marshall Islands', 'mq' => 'Martinique',
        'mr' => 'Mauritania', 'mu' => 'Mauritius', 'yt' => 'Mayotte', 'mx' => 'Mexico', 'fm' => 'Micronesia',
        'md' => 'Moldova', 'mc' => 'Monaco', 'mn' => 'Mongolia', 'me' => 'Montenegro', 'ms' => 'Montserrat',
        'ma' => 'Morocco', 'mz' => 'Mozambique', 'mm' => 'Myanmar', 'na' => 'Namibia', 'nr' => 'Nauru',
        'np' => 'Nepal', 'nl' => 'Netherlands', 'nc' => 'New Caledonia', 'nz' => 'New Zealand', 'ni' => 'Nicaragua',
        'ne' => 'Niger', 'ng' => 'Nigeria', 'nu' => 'Niue', 'nf' => 'Norfolk Island', 'mk' => 'North Macedonia',
        'mp' => 'Northern Mariana Islands', 'no' => 'Norway', 'om' => 'Oman', 'pk' => 'Pakistan', 'pw' => 'Palau',
        'ps' => 'Palestine', 'pa' => 'Panama', 'pg' => 'Papua New Guinea', 'py' => 'Paraguay', 'pe' => 'Peru',
        'ph' => 'Philippines', 'pn' => 'Pitcairn', 'pl' => 'Poland', 'pt' => 'Portugal', 'pr' => 'Puerto Rico',
        'qa' => 'Qatar', 're' => 'Réunion', 'ro' => 'Romania', 'ru' => 'Russia', 'rw' => 'Rwanda',
        'bl' => 'Saint Barthélemy', 'sh' => 'Saint Helena', 'kn' => 'Saint Kitts and Nevis', 'lc' => 'Saint Lucia', 'mf' => 'Saint Martin',
        'pm' => 'Saint Pierre and Miquelon', 'vc' => 'Saint Vincent and the Grenadines', 'ws' => 'Samoa', 'sm' => 'San Marino', 'st' => 'Sao Tome and Principe',
        'sa' => 'Saudi Arabia', 'sn' => 'Senegal', 'rs' => 'Serbia', 'sc' => 'Seychelles', 'sl' => 'Sierra Leone',
        'sg' => 'Singapore', 'sx' => 'Sint Maarten', 'sk' => 'Slovakia', 'si' => 'Slovenia', 'sb' => 'Solomon Islands',
        'so' => 'Somalia', 'za' => 'South Africa', 'gs' => 'South Georgia and the South Sandwich Islands', 'ss' => 'South Sudan', 'es' => 'Spain',
        'lk' => 'Sri Lanka', 'sd' => 'Sudan', 'sr' => 'Suriname', 'sj' => 'Svalbard and Jan Mayen', 'se' => 'Sweden',
        'ch' => 'Switzerland', 'sy' => 'Syria', 'tw' => 'Taiwan', 'tj' => 'Tajikistan', 'tz' => 'Tanzania',
        'th' => 'Thailand', 'tl' => 'Timor-Leste', 'tg' => 'Togo', 'tk' => 'Tokelau', 'to' => 'Tonga',
        'tt' => 'Trinidad and Tobago', 'tn' => 'Tunisia', 'tr' => 'Türkiye', 'tm' => 'Turkmenistan', 'tc' => 'Turks and Caicos Islands',
        'tv' => 'Tuvalu', 'ug' => 'Uganda', 'ua' => 'Ukraine', 'ae' => 'United Arab Emirates', 'gb' => 'United Kingdom',
        'us' => 'United States', 'um' => 'United States Minor Outlying Islands', 'uy' => 'Uruguay', 'uz' => 'Uzbekistan', 'vu' => 'Vanuatu',
        've' => 'Venezuela', 'vn' => 'Viet Nam', 'vg' => 'Virgin Islands (British)', 'vi' => 'Virgin Islands (U.S.)', 'wf' => 'Wallis and Futuna',
        'eh' => 'Western Sahara', 'ye' => 'Yemen', 'zm' => 'Zambia', 'zw' => 'Zimbabwe',
    ];

    /** Most asked for first, so they are a click away. */
    public const POPULAR = ['us', 'gb', 'ca', 'au', 'in', 'bd', 'de', 'fr', 'es', 'br', 'ae', 'sa', 'pk', 'ng', 'id', 'ph', 'my', 'sg', 'nl', 'it', 'mx', 'jp'];

    /** ISO 3166-1 numeric codes: Google Ads (and DataForSEO) name a country 2000 + this ("us" 840 → 2840). */
    private const NUMERIC = [
        'af' => 4, 'ax' => 248, 'al' => 8, 'dz' => 12, 'as' => 16, 'ad' => 20, 'ao' => 24, 'ai' => 660, 'aq' => 10, 'ag' => 28,
        'ar' => 32, 'am' => 51, 'aw' => 533, 'au' => 36, 'at' => 40, 'az' => 31, 'bs' => 44, 'bh' => 48, 'bd' => 50, 'bb' => 52,
        'by' => 112, 'be' => 56, 'bz' => 84, 'bj' => 204, 'bm' => 60, 'bt' => 64, 'bo' => 68, 'bq' => 535, 'ba' => 70, 'bw' => 72,
        'bv' => 74, 'br' => 76, 'io' => 86, 'bn' => 96, 'bg' => 100, 'bf' => 854, 'bi' => 108, 'cv' => 132, 'kh' => 116, 'cm' => 120,
        'ca' => 124, 'ky' => 136, 'cf' => 140, 'td' => 148, 'cl' => 152, 'cn' => 156, 'cx' => 162, 'cc' => 166, 'co' => 170, 'km' => 174,
        'cg' => 178, 'cd' => 180, 'ck' => 184, 'cr' => 188, 'ci' => 384, 'hr' => 191, 'cu' => 192, 'cw' => 531, 'cy' => 196, 'cz' => 203,
        'dk' => 208, 'dj' => 262, 'dm' => 212, 'do' => 214, 'ec' => 218, 'eg' => 818, 'sv' => 222, 'gq' => 226, 'er' => 232, 'ee' => 233,
        'sz' => 748, 'et' => 231, 'fk' => 238, 'fo' => 234, 'fj' => 242, 'fi' => 246, 'fr' => 250, 'gf' => 254, 'pf' => 258, 'tf' => 260,
        'ga' => 266, 'gm' => 270, 'ge' => 268, 'de' => 276, 'gh' => 288, 'gi' => 292, 'gr' => 300, 'gl' => 304, 'gd' => 308, 'gp' => 312,
        'gu' => 316, 'gt' => 320, 'gg' => 831, 'gn' => 324, 'gw' => 624, 'gy' => 328, 'ht' => 332, 'hm' => 334, 'va' => 336, 'hn' => 340,
        'hk' => 344, 'hu' => 348, 'is' => 352, 'in' => 356, 'id' => 360, 'ir' => 364, 'iq' => 368, 'ie' => 372, 'im' => 833, 'il' => 376,
        'it' => 380, 'jm' => 388, 'jp' => 392, 'je' => 832, 'jo' => 400, 'kz' => 398, 'ke' => 404, 'ki' => 296, 'kp' => 408, 'kr' => 410,
        'kw' => 414, 'kg' => 417, 'la' => 418, 'lv' => 428, 'lb' => 422, 'ls' => 426, 'lr' => 430, 'ly' => 434, 'li' => 438, 'lt' => 440,
        'lu' => 442, 'mo' => 446, 'mg' => 450, 'mw' => 454, 'my' => 458, 'mv' => 462, 'ml' => 466, 'mt' => 470, 'mh' => 584, 'mq' => 474,
        'mr' => 478, 'mu' => 480, 'yt' => 175, 'mx' => 484, 'fm' => 583, 'md' => 498, 'mc' => 492, 'mn' => 496, 'me' => 499, 'ms' => 500,
        'ma' => 504, 'mz' => 508, 'mm' => 104, 'na' => 516, 'nr' => 520, 'np' => 524, 'nl' => 528, 'nc' => 540, 'nz' => 554, 'ni' => 558,
        'ne' => 562, 'ng' => 566, 'nu' => 570, 'nf' => 574, 'mk' => 807, 'mp' => 580, 'no' => 578, 'om' => 512, 'pk' => 586, 'pw' => 585,
        'ps' => 275, 'pa' => 591, 'pg' => 598, 'py' => 600, 'pe' => 604, 'ph' => 608, 'pn' => 612, 'pl' => 616, 'pt' => 620, 'pr' => 630,
        'qa' => 634, 're' => 638, 'ro' => 642, 'ru' => 643, 'rw' => 646, 'bl' => 652, 'sh' => 654, 'kn' => 659, 'lc' => 662, 'mf' => 663,
        'pm' => 666, 'vc' => 670, 'ws' => 882, 'sm' => 674, 'st' => 678, 'sa' => 682, 'sn' => 686, 'rs' => 688, 'sc' => 690, 'sl' => 694,
        'sg' => 702, 'sx' => 534, 'sk' => 703, 'si' => 705, 'sb' => 90, 'so' => 706, 'za' => 710, 'gs' => 239, 'ss' => 728, 'es' => 724,
        'lk' => 144, 'sd' => 729, 'sr' => 740, 'sj' => 744, 'se' => 752, 'ch' => 756, 'sy' => 760, 'tw' => 158, 'tj' => 762, 'tz' => 834,
        'th' => 764, 'tl' => 626, 'tg' => 768, 'tk' => 772, 'to' => 776, 'tt' => 780, 'tn' => 788, 'tr' => 792, 'tm' => 795, 'tc' => 796,
        'tv' => 798, 'ug' => 800, 'ua' => 804, 'ae' => 784, 'gb' => 826, 'us' => 840, 'um' => 581, 'uy' => 858, 'uz' => 860, 'vu' => 548,
        've' => 862, 'vn' => 704, 'vg' => 92, 'vi' => 850, 'wf' => 876, 'eh' => 732, 'ye' => 887, 'zm' => 894, 'zw' => 716,
    ];

    /** Google Ads' location code for a country, or null. */
    public static function adsLocation(string $code): ?int
    {
        $numeric = self::NUMERIC[strtolower($code)] ?? null;

        return $numeric === null ? null : 2000 + $numeric;
    }

    public static function name(string $code): string
    {
        return self::ALL[strtolower($code)] ?? strtoupper($code);
    }

    public static function valid(string $code): bool
    {
        return isset(self::ALL[strtolower($code)]);
    }
}
