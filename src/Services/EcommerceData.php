<?php

namespace Acme\CmsDashboard\Services;

class EcommerceData
{
    /**
     * Get a truly comprehensive list of countries and states.
     */
    public static function getCountriesWithStates(bool $filtered = true): array
    {
        $allCountries = [
            'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan',
            'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi',
            'Cabo Verde', 'Cambodia', 'Cameroon', 'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czechia',
            'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia',
            'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana',
            'Haiti', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Ivory Coast',
            'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg',
            'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar',
            'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal',
            'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria',
            'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu',
            'Uganda', 'Ukraine', 'United Arab Emirates (UAE)', 'United Kingdom (UK)', 'United States (US)', 'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam',
            'Yemen', 'Zambia', 'Zimbabwe'
        ];

        if (!$filtered) {
            $finalCountries = $allCountries;
        } else {
            // Filter based on shop settings
            $sellingMode = get_shop_option('shop_selling_locations', 'all');
            $shippingMode = get_shop_option('shop_shipping_locations', 'all');

            $allowedCountries = $allCountries;

            // Apply Selling Restrictions
            if ($sellingMode === 'specific') {
                $allowedCountries = (array) get_shop_option('shop_selling_specific_countries', []);
            } elseif ($sellingMode === 'all_except') {
                $except = (array) get_shop_option('shop_selling_except_countries', []);
                $allowedCountries = array_filter($allCountries, fn($c) => !in_array($c, $except));
            }

            // Apply Shipping Restrictions
            $finalCountries = $allowedCountries;
            if ($shippingMode === 'specific') {
                $finalCountries = (array) get_shop_option('shop_shipping_specific_countries', []);
            } elseif ($shippingMode === 'all_countries') {
                $finalCountries = $allCountries;
            } elseif ($shippingMode === 'disabled') {
                return [];
            }
        }

        $list = [];
        foreach ($finalCountries as $country) {
            if ($country === 'Bangladesh') {
                $list['Bangladesh'] = 'Bangladesh';
                $list['Bangladesh - Dhaka'] = 'Bangladesh - Dhaka';
                $list['Bangladesh - Chittagong'] = 'Bangladesh - Chittagong';
                $list['Bangladesh - Rajshahi'] = 'Bangladesh - Rajshahi';
                $list['Bangladesh - Khulna'] = 'Bangladesh - Khulna';
                $list['Bangladesh - Sylhet'] = 'Bangladesh - Sylhet';
                $list['Bangladesh - Barisal'] = 'Bangladesh - Barisal';
                $list['Bangladesh - Rangpur'] = 'Bangladesh - Rangpur';
                $list['Bangladesh - Mymensingh'] = 'Bangladesh - Mymensingh';
            } elseif ($country === 'United States (US)') {
                $list['United States (US)'] = 'United States (US)';
                $states = ['Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut', 'Delaware', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming'];
                foreach ($states as $state) {
                    $list["United States (US) - $state"] = "United States (US) - $state";
                }
            } elseif ($country === 'India') {
                $list['India'] = 'India';
                $states = ['West Bengal', 'Delhi', 'Maharashtra', 'Karnataka', 'Tamil Nadu', 'Gujarat', 'Rajasthan', 'Uttar Pradesh'];
                foreach ($states as $state) {
                    $list["India - $state"] = "India - $state";
                }
            } else {
                $list[$country] = $country;
            }
        }

        ksort($list);
        return $list;
    }

    public static function getCountries(bool $filtered = true): array
    {
        return self::getCountriesWithStates($filtered);
    }

    public static function getCurrencies(): array
    {
        $currencies = [
            'AED' => 'United Arab Emirates Dirham (د.إ) - AED',
            'AFN' => 'Afghan Afghani (؋) - AFN',
            'ALL' => 'Albanian Lek (L) - ALL',
            'AMD' => 'Armenian Dram (֏) - AMD',
            'ANG' => 'Netherlands Antillean Guilder (ƒ) - ANG',
            'AOA' => 'Angolan Kwanza (Kz) - AOA',
            'ARS' => 'Argentine Peso ($) - ARS',
            'AUD' => 'Australian Dollar ($) - AUD',
            'AWG' => 'Aruban Florin (ƒ) - AWG',
            'AZN' => 'Azerbaijani Manat (₼) - AZN',
            'BAM' => 'Bosnia-Herzegovina Convertible Mark (KM) - BAM',
            'BBD' => 'Barbadian Dollar ($) - BBD',
            'BDT' => 'Bangladeshi Taka (৳) - BDT',
            'BGN' => 'Bulgarian Lev (лв) - BGN',
            'BHD' => 'Bahraini Dinar (.د.ب) - BHD',
            'BIF' => 'Burundian Franc (FBu) - BIF',
            'BMD' => 'Bermudian Dollar ($) - BMD',
            'BND' => 'Brunei Dollar ($) - BND',
            'BOB' => 'Bolivian Boliviano (Bs.) - BOB',
            'BRL' => 'Brazilian Real (R$) - BRL',
            'BSD' => 'Bahamian Dollar ($) - BSD',
            'BTN' => 'Bhutanese Ngultrum (Nu.) - BTN',
            'BWP' => 'Botswanan Pula (P) - BWP',
            'BYN' => 'Belarusian Ruble (Br) - BYN',
            'BZD' => 'Belize Dollar ($) - BZD',
            'CAD' => 'Canadian Dollar ($) - CAD',
            'CDF' => 'Congolese Franc (FC) - CDF',
            'CHF' => 'Swiss Franc (CHF) - CHF',
            'CLP' => 'Chilean Peso ($) - CLP',
            'CNY' => 'Chinese Yuan (¥) - CNY',
            'COP' => 'Colombian Peso ($) - COP',
            'CRC' => 'Costa Rican Colón (₡) - CRC',
            'CUP' => 'Cuban Peso ($) - CUP',
            'CVE' => 'Cape Verdean Escudo ($) - CVE',
            'CZK' => 'Czech Koruna (Kč) - CZK',
            'DJF' => 'Djiboutian Franc (Fdj) - DJF',
            'DKK' => 'Danish Krone (kr) - DKK',
            'DOP' => 'Dominican Peso ($) - DOP',
            'DZD' => 'Algerian Dinar (د.ج) - DZD',
            'EGP' => 'Egyptian Pound (E£) - EGP',
            'ERN' => 'Eritrean Nakfa (Nfk) - ERN',
            'ETB' => 'Ethiopian Birr (Br) - ETB',
            'EUR' => 'Euro (€) - EUR',
            'FJD' => 'Fijian Dollar ($) - FJD',
            'FKP' => 'Falkland Islands Pound (£) - FKP',
            'GBP' => 'British Pound Sterling (£) - GBP',
            'GEL' => 'Georgian Lari (₾) - GEL',
            'GHS' => 'Ghanaian Cedi (₵) - GHS',
            'GIP' => 'Gibraltar Pound (£) - GIP',
            'GMD' => 'Gambian Dalasi (D) - GMD',
            'GNF' => 'Guinean Franc (FG) - GNF',
            'GTQ' => 'Guatemalan Quetzal (Q) - GTQ',
            'GYD' => 'Guyanese Dollar ($) - GYD',
            'HKD' => 'Hong Kong Dollar ($) - HKD',
            'HNL' => 'Honduran Lempira (L) - HNL',
            'HRK' => 'Croatian Kuna (kn) - HRK',
            'HTG' => 'Haitian Gourde (G) - HTG',
            'HUF' => 'Hungarian Forint (Ft) - HUF',
            'IDR' => 'Indonesian Rupiah (Rp) - IDR',
            'ILS' => 'Israeli New Shekel (₪) - ILS',
            'INR' => 'Indian Rupee (₹) - INR',
            'IQD' => 'Iraqi Dinar (ع.দ) - IQD',
            'IRR' => 'Iranian Rial (﷼) - IRR',
            'ISK' => 'Icelandic Króna (kr) - ISK',
            'JMD' => 'Jamaican Dollar ($) - JMD',
            'JOD' => 'Jordanian Dinar (د.ا) - JOD',
            'JPY' => 'Japanese Yen (¥) - JPY',
            'KES' => 'Kenyan Shilling (KSh) - KES',
            'KGS' => 'Kyrgystani Som (с) - KGS',
            'KHR' => 'Cambodian Riel (៛) - KHR',
            'KMF' => 'Comorian Franc (CF) - KMF',
            'KPW' => 'North Korean Won (₩) - KPW',
            'KRW' => 'South Korean Won (₩) - KRW',
            'KWD' => 'Kuwaiti Dinar (د.ك) - KWD',
            'KYD' => 'Cayman Islands Dollar ($) - KYD',
            'KZT' => 'Kazakhstani Tenge (₸) - KZT',
            'LAK' => 'Laotian Kip (₭) - LAK',
            'LBP' => 'Lebanese Pound (L£) - LBP',
            'LKR' => 'Sri Lankan Rupee (Rs) - LKR',
            'LRD' => 'Liberian Dollar ($) - LRD',
            'LSL' => 'Lesotho Loti (L) - LSL',
            'LYD' => 'Libyan Dinar (ل.د) - LYD',
            'MAD' => 'Moroccan Dirham (د.ম.) - MAD',
            'MDL' => 'Moldovan Leu (L) - MDL',
            'MGA' => 'Malagasy Ariary (Ar) - MGA',
            'MKD' => 'Macedonian Denar (ден) - MKD',
            'MMK' => 'Myanmar Kyat (K) - MMK',
            'MNT' => 'Mongolian Tugrik (₮) - MNT',
            'MOP' => 'Macanese Pataca (P) - MOP',
            'MRU' => 'Mauritanian Ouguiya (UM) - MRU',
            'MUR' => 'Mauritian Rupee (₨) - MUR',
            'MVR' => 'Maldivian Rufiyaa (Rf) - MVR',
            'MWK' => 'Malawian Kwacha (MK) - MWK',
            'MXN' => 'Mexican Peso ($) - MXN',
            'MYR' => 'Malaysian Ringgit (RM) - MYR',
            'MZN' => 'Mozambican Metical (MT) - MZN',
            'NAD' => 'Namibian Dollar ($) - NAD',
            'NGN' => 'Nigerian Naira (₦) - NGN',
            'NIO' => 'Nicaraguan Córdoba (C$) - NIO',
            'NOK' => 'Norwegian Krone (kr) - NOK',
            'NPR' => 'Nepalese Rupee (₨) - NPR',
            'NZD' => 'New Zealand Dollar ($) - NZD',
            'OMR' => 'Omani Rial (ر.ع.) - OMR',
            'PAB' => 'Panamanian Balboa (B/.) - PAB',
            'PEN' => 'Peruvian Sol (S/.) - PEN',
            'PGK' => 'Papua New Guinean Kina (K) - PGK',
            'PHP' => 'Philippine Peso (₱) - PHP',
            'PKR' => 'Pakistani Rupee (₨) - PKR',
            'PLN' => 'Polish Zloty (zł) - PLN',
            'PYG' => 'Paraguayan Guarani (₲) - PYG',
            'QAR' => 'Qatari Rial (ر.ق) - QAR',
            'RON' => 'Romanian Leu (lei) - RON',
            'RSD' => 'Serbian Dinar (дин.) - RSD',
            'RUB' => 'Russian Ruble (₽) - RUB',
            'RWF' => 'Rwandan Franc (FRw) - RWF',
            'SAR' => 'Saudi Riyal (﷼) - SAR',
            'SBD' => 'Solomon Islands Dollar ($) - SBD',
            'SCR' => 'Seychellois Rupee (₨) - SCR',
            'SDG' => 'Sudanese Pound (S£) - SDG',
            'SEK' => 'Swedish Krona (kr) - SEK',
            'SGD' => 'Singapore Dollar ($) - SGD',
            'SLL' => 'Sierra Leonean Leone (Le) - SLL',
            'SOS' => 'Somali Shilling (Sh) - SOS',
            'SRD' => 'Surinamese Dollar ($) - SRD',
            'SSP' => 'South Sudanese Pound (£) - SSP',
            'STN' => 'São Tomé & Príncipe Dobra (Db) - STN',
            'SYP' => 'Syrian Pound (LS) - SYP',
            'SZL' => 'Swazi Lilangeni (L) - SZL',
            'THB' => 'Thai Baht (฿) - THB',
            'TJS' => 'Tajikistani Somoni (SM) - TJS',
               'TOP' => 'Tongan Paʻanga (T$) - TOP',
            'TRY' => 'Turkish Lira (₺) - TRY',
            'TTD' => 'Trinidad & Tobago Dollar ($) - TTD',
            'TWD' => 'New Taiwan Dollar ($) - TWD',
            'TZS' => 'Tanzanian Shilling (TSh) - TZS',
            'UAH' => 'Ukrainian Hryvnia (₴) - UAH',
            'UGX' => 'Ugandan Shilling (USh) - UGX',
            'USD' => 'United States (US) Dollar ($) - USD',
            'UYU' => 'Uruguayan Peso ($) - UYU',
            'UZS' => 'Uzbekistani Som (с) - UZS',
            'VES' => 'Venezuelan Bolívar (Bs.S) - VES',
            'VND' => 'Vietnamese Dong (₫) - VND',
            'VUV' => 'Vanuatu Vatu (Vt) - VUV',
            'WST' => 'Samoan Tala (T) - WST',
            'XAF' => 'Central African CFA Franc (FCFA) - XAF',
            'XCD' => 'East Caribbean Dollar ($) - XCD',
            'XOF' => 'West African CFA Franc (CFA) - XOF',
            'XPF' => 'CFP Franc (₣) - XPF',
            'YER' => 'Yemeni Rial (﷼) - YER',
            'ZAR' => 'South African Rand (R) - ZAR',
            'ZMW' => 'Zambian Kwacha (ZK) - ZMW',
            'ZWL' => 'Zimbabwean Dollar ($) - ZWL',
        ];
        ksort($currencies);
        return $currencies;
    }

    public static function getCurrencySymbol(string $code): string
    {
        $currencies = self::getCurrencies();
        $label = $currencies[$code] ?? '';
        // Extract symbol from (symbol) part
        if (preg_match_all('/\(([^)]+)\)/', $label, $matches)) {
            return end($matches[1]);
        }
        return '$';
    }
}
