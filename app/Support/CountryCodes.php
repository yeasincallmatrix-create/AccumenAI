<?php

namespace App\Support;

/**
 * Country dial-code reference used by the phone inputs.
 * Key = official English country name, value = dial code without the leading '+'.
 */
class CountryCodes
{
    /** @var array<string,string> */
    public const CODES = [
        'Bangladesh' => '880',
        'India' => '91',
        'Pakistan' => '92',
        'Sri Lanka' => '94',
        'Nepal' => '977',
        'Bhutan' => '975',
        'Maldives' => '960',
        'Afghanistan' => '93',
        'Myanmar' => '95',
        'Thailand' => '66',
        'Vietnam' => '84',
        'Laos' => '856',
        'Cambodia' => '855',
        'Malaysia' => '60',
        'Singapore' => '65',
        'Indonesia' => '62',
        'Philippines' => '63',
        'Brunei' => '673',
        'Timor-Leste' => '670',
        'China' => '86',
        'Hong Kong' => '852',
        'Macau' => '853',
        'Taiwan' => '886',
        'Japan' => '81',
        'South Korea' => '82',
        'North Korea' => '850',
        'Mongolia' => '976',
        'Australia' => '61',
        'New Zealand' => '64',
        'Fiji' => '679',
        'Papua New Guinea' => '675',
        'Samoa' => '685',
        'Tonga' => '676',
        'Solomon Islands' => '677',
        'Vanuatu' => '678',
        'Saudi Arabia' => '966',
        'United Arab Emirates' => '971',
        'Qatar' => '974',
        'Bahrain' => '973',
        'Kuwait' => '965',
        'Oman' => '968',
        'Yemen' => '967',
        'Iraq' => '964',
        'Iran' => '98',
        'Jordan' => '962',
        'Lebanon' => '961',
        'Syria' => '963',
        'Israel' => '972',
        'Palestine' => '970',
        'Turkey' => '90',
        'Cyprus' => '357',
        'Georgia' => '995',
        'Armenia' => '374',
        'Azerbaijan' => '994',
        'Kazakhstan' => '7',
        'Uzbekistan' => '998',
        'Turkmenistan' => '993',
        'Kyrgyzstan' => '996',
        'Tajikistan' => '992',
        'Russia' => '7',
        'Ukraine' => '380',
        'Belarus' => '375',
        'Moldova' => '373',
        'Lithuania' => '370',
        'Latvia' => '371',
        'Estonia' => '372',
        'Poland' => '48',
        'Czech Republic' => '420',
        'Slovakia' => '421',
        'Hungary' => '36',
        'Romania' => '40',
        'Bulgaria' => '359',
        'Serbia' => '381',
        'Croatia' => '385',
        'Slovenia' => '386',
        'Bosnia and Herzegovina' => '387',
        'Montenegro' => '382',
        'North Macedonia' => '389',
        'Albania' => '355',
        'Kosovo' => '383',
        'Greece' => '30',
        'United Kingdom' => '44',
        'Ireland' => '353',
        'France' => '33',
        'Germany' => '49',
        'Spain' => '34',
        'Portugal' => '351',
        'Italy' => '39',
        'Switzerland' => '41',
        'Austria' => '43',
        'Netherlands' => '31',
        'Belgium' => '32',
        'Luxembourg' => '352',
        'Denmark' => '45',
        'Norway' => '47',
        'Sweden' => '46',
        'Finland' => '358',
        'Iceland' => '354',
        'Egypt' => '20',
        'Morocco' => '212',
        'Algeria' => '213',
        'Tunisia' => '216',
        'Libya' => '218',
        'Sudan' => '249',
        'South Sudan' => '211',
        'Ethiopia' => '251',
        'Eritrea' => '291',
        'Djibouti' => '253',
        'Somalia' => '252',
        'Kenya' => '254',
        'Uganda' => '256',
        'Tanzania' => '255',
        'Rwanda' => '250',
        'Burundi' => '257',
        'DR Congo' => '243',
        'Congo' => '242',
        'Gabon' => '241',
        'Cameroon' => '237',
        'Nigeria' => '234',
        'Ghana' => '233',
        'Senegal' => '221',
        'Ivory Coast' => '225',
        'Mali' => '223',
        'Burkina Faso' => '226',
        'Niger' => '227',
        'Chad' => '235',
        'Central African Republic' => '236',
        'Mauritania' => '222',
        'Gambia' => '220',
        'Guinea' => '224',
        'Guinea-Bissau' => '245',
        'Sierra Leone' => '232',
        'Liberia' => '231',
        'Togo' => '228',
        'Benin' => '229',
        'Equatorial Guinea' => '240',
        'Angola' => '244',
        'Zambia' => '260',
        'Zimbabwe' => '263',
        'Malawi' => '265',
        'Mozambique' => '258',
        'Botswana' => '267',
        'Namibia' => '264',
        'South Africa' => '27',
        'Lesotho' => '266',
        'Eswatini' => '268',
        'Madagascar' => '261',
        'Mauritius' => '230',
        'Seychelles' => '248',
        'Comoros' => '269',
        'Cape Verde' => '238',
        'United States' => '1',
        'Canada' => '1',
        'Mexico' => '52',
        'Guatemala' => '502',
        'Belize' => '501',
        'Honduras' => '504',
        'El Salvador' => '503',
        'Nicaragua' => '505',
        'Costa Rica' => '506',
        'Panama' => '507',
        'Cuba' => '53',
        'Jamaica' => '1876',
        'Haiti' => '509',
        'Dominican Republic' => '1809',
        'Puerto Rico' => '1787',
        'Bahamas' => '1242',
        'Trinidad and Tobago' => '1868',
        'Barbados' => '1246',
        'Guyana' => '592',
        'Suriname' => '597',
        'Venezuela' => '58',
        'Colombia' => '57',
        'Ecuador' => '593',
        'Peru' => '51',
        'Brazil' => '55',
        'Bolivia' => '591',
        'Paraguay' => '595',
        'Uruguay' => '598',
        'Argentina' => '54',
        'Chile' => '56',
        'Greenland' => '299',
    ];

    /** @var array<string,string>|null */
    private static ?array $codesByLength = null;

    /**
     * @return array<string,string>
     */
    public static function all(): array
    {
        return self::CODES;
    }

    public static function codeFor(?string $country): string
    {
        if ($country !== null && $country !== '' && isset(self::CODES[$country])) {
            return self::CODES[$country];
        }

        return '880';
    }

    /**
     * Example national mobile format per country, used as the placeholder
     * suggestion on phone inputs. Formats omit the leading dial code (the
     * input group shows that separately). Countries not listed fall back to a
     * dial-code-prefixed generic pattern.
     *
     * @var array<string,string>
     */
    public const PHONE_EXAMPLES = [
        'Bangladesh' => '017XXXXXXXX',
        'India' => '98765 XXXXX',
        'Pakistan' => '03XX XXXXXXX',
        'Sri Lanka' => '07X XXX XXXX',
        'Nepal' => '98XXXXXXXX',
        'Maldives' => '7XXXXXXXX',
        'Bhutan' => '17 XXXXX',
        'Afghanistan' => '07X XXX XXXX',
        'Myanmar' => '09XXXXXXXX',
        'Thailand' => '08X XXX XXXX',
        'Vietnam' => '09X XXX XXXX',
        'Laos' => '20XX XXX XXX',
        'Cambodia' => '01X XXX XXX',
        'Malaysia' => '01X XXX XXXX',
        'Singapore' => '8XXX XXXX',
        'Indonesia' => '08XX-XXXX-XXXX',
        'Philippines' => '0917 XXX XXXX',
        'Brunei' => '71X XXXX',
        'Timor-Leste' => '77XX XXXX',
        'China' => '13X XXXX XXXX',
        'Hong Kong' => '5XXX XXXX',
        'Macau' => '6XXX XXXX',
        'Taiwan' => '09XX XXX XXX',
        'Japan' => '090-XXXX-XXXX',
        'South Korea' => '010-XXXX-XXXX',
        'Mongolia' => '88XX XXXX',
        'Saudi Arabia' => '05X XXX XXXX',
        'United Arab Emirates' => '05X XXX XXXX',
        'Qatar' => '3XXX XXXX',
        'Bahrain' => '3XXX XXXX',
        'Kuwait' => '5XXXXXXX',
        'Oman' => '9XXX XXXX',
        'Iraq' => '07X XXX XXXX',
        'Iran' => '09XX XXX XXXX',
        'Jordan' => '07X XXXX XXX',
        'Lebanon' => '03 XXX XXX',
        'Israel' => '05X-XXX-XXXX',
        'Palestine' => '05X XXX XXXX',
        'Turkey' => '05XX XXX XX XX',
        'Russia' => '9XX XXX-XX-XX',
        'Ukraine' => '0XX XXX XX XX',
        'United States' => '(555) 123-4567',
        'Canada' => '(555) 123-4567',
        'Mexico' => '55 1234 5678',
        'Brazil' => '(11) 9XXXX-XXXX',
        'United Kingdom' => '07XXX XXXXXX',
        'France' => '06 12 34 56 78',
        'Germany' => '015X XXXXXXX',
        'Italy' => '3XX XXX XXXX',
        'Spain' => '6XX XXX XXX',
        'Portugal' => '9XX XXX XXX',
        'Australia' => '04XX XXX XXX',
        'New Zealand' => '021 XXX XXXX',
    ];

    public static function phoneExampleFor(?string $country): string
    {
        if ($country !== null && isset(self::PHONE_EXAMPLES[$country])) {
            return self::PHONE_EXAMPLES[$country];
        }

        $code = self::codeFor($country);

        return $code === '880' ? '017XXXXXXXX' : $code.' XXX XXXXX';
    }

    /**
     * National mobile digit lengths inc. trunk `0` where applicable.
     * Derived from docs/global_mobile_phone_lengths.md (Gemini + ITU E.164).
     * Value = [min,max] inclusive. Ranges (e.g. Indonesia 10-12) preserve hints
     * as "Valid 10–12 digits". Fallback [7,12] when country not listed.
     *
     * @var array<string,array{int,int}>
     */
    public const NATIONAL_LENGTHS = [
        // Americas
        'United States' => [10, 10],
        'Canada' => [10, 10],
        'Mexico' => [10, 10],
        'Brazil' => [11, 11],
        'Argentina' => [10, 10],
        'Colombia' => [10, 10],
        'Chile' => [9, 9],
        'Peru' => [9, 9],
        'Venezuela' => [10, 10],
        'Ecuador' => [9, 9],
        'Guatemala' => [8, 8],
        'Cuba' => [8, 8],
        'Dominican Republic' => [10, 10],
        'Haiti' => [8, 8],
        'Bolivia' => [8, 8],
        'Uruguay' => [8, 8],
        'Paraguay' => [9, 9],
        'Costa Rica' => [8, 8],
        'Panama' => [8, 8],
        'Jamaica' => [10, 10],
        'Puerto Rico' => [10, 10],
        'Bahamas' => [10, 10],
        'Trinidad and Tobago' => [10, 10],
        'Barbados' => [10, 10],
        'Guyana' => [7, 7],
        'Suriname' => [7, 7],
        'Belize' => [7, 7],
        'Honduras' => [8, 8],
        'El Salvador' => [8, 8],
        'Nicaragua' => [8, 8],
        'Greenland' => [6, 6],
        // Asia & Oceania
        'China' => [11, 11],
        'India' => [10, 10],
        'Indonesia' => [10, 12],
        'Pakistan' => [10, 10],
        'Bangladesh' => [11, 11],
        'Japan' => [10, 10],
        'Philippines' => [10, 10],
        'Vietnam' => [9, 9],
        'South Korea' => [10, 10],
        'Taiwan' => [9, 9],
        'Thailand' => [9, 9],
        'Malaysia' => [9, 10],
        'Singapore' => [8, 8],
        'Sri Lanka' => [9, 9],
        'Myanmar' => [8, 9],
        'Australia' => [10, 10],
        'New Zealand' => [8, 10],
        'Papua New Guinea' => [8, 8],
        'Fiji' => [7, 7],
        'Samoa' => [7, 7],
        'Tonga' => [7, 7],
        'Solomon Islands' => [7, 7],
        'Vanuatu' => [7, 7],
        'Hong Kong' => [8, 8],
        'Macau' => [8, 8],
        'Mongolia' => [8, 8],
        'North Korea' => [8, 10],
        'Laos' => [9, 9],
        'Cambodia' => [8, 8],
        'Brunei' => [7, 7],
        'Timor-Leste' => [7, 7],
        // Europe
        'United Kingdom' => [11, 11],
        'Germany' => [10, 11],
        'France' => [9, 9],
        'Italy' => [10, 10],
        'Spain' => [9, 9],
        'Russia' => [10, 10],
        'Ukraine' => [9, 9],
        'Poland' => [9, 9],
        'Romania' => [9, 9],
        'Netherlands' => [9, 9],
        'Belgium' => [9, 9],
        'Greece' => [10, 10],
        'Czech Republic' => [9, 9],
        'Portugal' => [9, 9],
        'Sweden' => [9, 9],
        'Hungary' => [9, 9],
        'Austria' => [10, 11],
        'Switzerland' => [9, 9],
        'Norway' => [8, 8],
        'Denmark' => [8, 8],
        'Finland' => [9, 10],
        'Ireland' => [9, 9],
        'Bulgaria' => [9, 9],
        'Serbia' => [9, 9],
        'Croatia' => [9, 9],
        'Slovenia' => [9, 9],
        'Bosnia and Herzegovina' => [9, 9],
        'Montenegro' => [8, 8],
        'North Macedonia' => [8, 8],
        'Albania' => [9, 9],
        'Kosovo' => [8, 8],
        'Cyprus' => [8, 8],
        'Georgia' => [9, 9],
        'Armenia' => [8, 8],
        'Azerbaijan' => [9, 9],
        'Kazakhstan' => [10, 10],
        'Uzbekistan' => [9, 9],
        'Turkmenistan' => [8, 8],
        'Kyrgyzstan' => [9, 9],
        'Tajikistan' => [9, 9],
        'Belarus' => [9, 9],
        'Moldova' => [8, 8],
        'Lithuania' => [8, 8],
        'Latvia' => [8, 8],
        'Estonia' => [8, 8],
        'Slovakia' => [9, 9],
        'Iceland' => [7, 7],
        'Luxembourg' => [9, 9],
        'Turkey' => [10, 10],
        'Israel' => [9, 9],
        'Palestine' => [9, 9],
        'Lebanon' => [8, 8],
        'Jordan' => [9, 9],
        'Syria' => [9, 9],
        // Middle East & Africa
        'Nigeria' => [10, 10],
        'Egypt' => [10, 10],
        'Ethiopia' => [9, 9],
        'South Africa' => [9, 9],
        'Kenya' => [9, 9],
        'Tanzania' => [9, 9],
        'Algeria' => [9, 9],
        'Uganda' => [9, 9],
        'Sudan' => [9, 9],
        'Morocco' => [9, 9],
        'Saudi Arabia' => [10, 10],
        'United Arab Emirates' => [10, 10],
        'Qatar' => [8, 8],
        'Kuwait' => [8, 8],
        'Oman' => [8, 8],
        'Ghana' => [9, 9],
        'Ivory Coast' => [10, 10],
        'Senegal' => [9, 9],
        'Mali' => [8, 8],
        'Burkina Faso' => [8, 8],
        'Niger' => [8, 8],
        'Chad' => [8, 8],
        'Central African Republic' => [7, 7],
        'Mauritania' => [8, 8],
        'Gambia' => [7, 7],
        'Guinea' => [8, 8],
        'Guinea-Bissau' => [7, 7],
        'Sierra Leone' => [8, 8],
        'Liberia' => [7, 7],
        'Togo' => [8, 8],
        'Benin' => [8, 8],
        'Equatorial Guinea' => [9, 9],
        'Angola' => [9, 9],
        'Zambia' => [9, 9],
        'Zimbabwe' => [9, 9],
        'Malawi' => [9, 9],
        'Mozambique' => [9, 9],
        'Botswana' => [7, 7],
        'Namibia' => [7, 7],
        'Lesotho' => [8, 8],
        'Eswatini' => [8, 8],
        'Madagascar' => [9, 9],
        'Mauritius' => [8, 8],
        'Seychelles' => [7, 7],
        'Comoros' => [7, 7],
        'Cape Verde' => [7, 7],
        'Yemen' => [9, 9],
        'Iraq' => [10, 10],
        'Iran' => [10, 10],
        'Bahrain' => [8, 8],
        'Tunisia' => [8, 8],
        'Libya' => [9, 9],
        'South Sudan' => [9, 9],
        'Eritrea' => [7, 7],
        'Djibouti' => [8, 8],
        'Somalia' => [8, 8],
    ];

    /**
     * National length range for a country, fallback [7,12] when not listed.
     *
     * @return array{int,int}
     */
    public static function nationalLengthFor(?string $country): array
    {
        if ($country !== null && isset(self::NATIONAL_LENGTHS[$country])) {
            return self::NATIONAL_LENGTHS[$country];
        }
        return [7, 12];
    }

    /**
     * Longest dial-code that prefixes the given digit string (no leading '+'),
     * or null when none matches.
     */
    public static function matchPrefix(string $digits): ?string
    {
        if ($digits === '') {
            return null;
        }
        if (self::$codesByLength === null) {
            $codes = array_values(self::CODES);
            usort($codes, fn ($a, $b) => strlen($b) - strlen($a));
            self::$codesByLength = $codes;
        }

        foreach (self::$codesByLength as $code) {
            if (str_starts_with($digits, $code)) {
                return $code;
            }
        }

        return null;
    }
}
