<?php
/**
 * Manage money formatting and currencies
 * @package Am_Invoice
 */
class Am_Currency {
    static $currencyList = [
        'AED' => ['name' => 'UAE Dirham', 'country' => ['AE'], 'numcode' => '784', 'precision' => 2],
        'AFN' => ['name' => 'Afghani', 'country' => ['AF'], 'numcode' => '971', 'precision' => 2],
        'ALL' => ['name' => 'Lek', 'country' => ['AL'], 'numcode' => '008', 'precision' => 2],
        'AMD' => ['name' => 'Armenian Dram', 'country' => ['AM'], 'numcode' => '051', 'precision' => 2],
        'ANG' => ['name' => 'Netherlands Antillean Guilder', 'country' => ['CURACAO', 'SINT MAARTEN (DUTCH PART)'], 'numcode' => '532', 'precision' => 2],
        'AOA' => ['name' => 'Kwanza', 'country' => ['AO'], 'numcode' => '973', 'precision' => 2],
        'ARS' => ['name' => 'Argentine Peso', 'country' => ['AR'], 'numcode' => '032', 'precision' => 2],
        'AUD' => ['name' => 'Australian Dollar', 'country' => ['AU', 'CX', 'CC', 'HM', 'KI', 'NR', 'NF', 'TV'], 'numcode' => '036', 'format' => '%2$s%1$s', 'symbol' => '$', 'precision' => 2],
        'AWG' => ['name' => 'Aruban Florin', 'country' => ['AW'], 'numcode' => '533', 'precision' => 2],
        'AZN' => ['name' => 'Azerbaijanian Manat', 'country' => ['AZ'], 'numcode' => '944', 'precision' => 2],
        'BAM' => ['name' => 'Convertible Mark', 'country' => ['BOSNIA & HERZEGOVINA'], 'numcode' => '977', 'precision' => 2],
        'BBD' => ['name' => 'Barbados Dollar', 'country' => ['BB'], 'numcode' => '052', 'precision' => 2],
        'BDT' => ['name' => 'Taka', 'country' => ['BD'], 'numcode' => '050', 'precision' => 2],
        'BGN' => ['name' => 'Bulgarian Lev', 'country' => ['BG'], 'numcode' => '975', 'precision' => 2],
        'BHD' => ['name' => 'Bahraini Dinar', 'country' => ['BH'], 'numcode' => '048', 'precision' => 3],
        'BIF' => ['name' => 'Burundi Franc', 'country' => ['BI'], 'numcode' => '108', 'precision' => 0],
        'BMD' => ['name' => 'Bermudian Dollar', 'country' => ['BM'], 'numcode' => '060', 'precision' => 2],
        'BND' => ['name' => 'Brunei Dollar', 'country' => ['BRUNEI DARUSSALAM'], 'numcode' => '096', 'precision' => 2],
        'BOB' => ['name' => 'Boliviano', 'country' => ['BOLIVIA, PLURINATIONAL STATE OF'], 'numcode' => '068', 'precision' => 2],
        'BOV' => ['name' => 'Mvdol', 'country' => ['BOLIVIA, PLURINATIONAL STATE OF'], 'numcode' => '984', 'precision' => 2],
        'BRL' => ['name' => 'Brazilian Real', 'country' => ['BR'], 'numcode' => '986', 'format' => '%2$s%1$s', 'symbol' => 'R$', 'precision' => 2],
        'BSD' => ['name' => 'Bahamian Dollar', 'country' => ['BS'], 'numcode' => '044', 'precision' => 2],
        'BTN' => ['name' => 'Ngultrum', 'country' => ['BT'], 'numcode' => '064', 'precision' => 2],
        'BWP' => ['name' => 'Pula', 'country' => ['BW'], 'numcode' => '072', 'precision' => 2],
        'BYR' => ['name' => 'Belarussian Ruble', 'country' => ['BY'], 'numcode' => '974', 'precision' => 0],
        'BZD' => ['name' => 'Belize Dollar', 'country' => ['BZ'], 'numcode' => '084', 'precision' => 2],
        'CAD' => ['name' => 'Canadian Dollar', 'country' => ['CA'], 'numcode' => '124', 'precision' => 2],
        'CDF' => ['name' => 'Congolese Franc', 'country' => ['CONGO, THE DEMOCRATIC REPUBLIC OF'], 'numcode' => '976', 'precision' => 2],
        'CHE' => ['name' => 'WIR Euro', 'country' => ['CH'], 'numcode' => '947', 'precision' => 2],
        'CHF' => ['name' => 'Swiss Franc', 'country' => ['LI', 'CH'], 'numcode' => '756', 'precision' => 2],
        'CHW' => ['name' => 'WIR Franc', 'country' => ['CH'], 'numcode' => '948', 'precision' => 2],
        'CLF' => ['name' => 'Unidades de fomento', 'country' => ['CL'], 'numcode' => '990', 'precision' => 0],
        'CLP' => ['name' => 'Chilean Peso', 'country' => ['CL'], 'numcode' => '152', 'precision' => 0],
        'CNY' => ['name' => 'Yuan Renminbi', 'country' => ['CN'], 'numcode' => '156', 'precision' => 2],
        'COP' => ['name' => 'Colombian Peso', 'country' => ['CO'], 'numcode' => '170', 'precision' => 2],
        'COU' => ['name' => 'Unidad de Valor Real', 'country' => ['CO'], 'numcode' => '970', 'precision' => 2],
        'CRC' => ['name' => 'Costa Rican Colon', 'country' => ['CR'], 'numcode' => '188', 'precision' => 2],
        'CUC' => ['name' => 'Peso Convertible', 'country' => ['CU'], 'numcode' => '931', 'precision' => 2],
        'CUP' => ['name' => 'Cuban Peso', 'country' => ['CU'], 'numcode' => '192', 'precision' => 2],
        'CVE' => ['name' => 'Cape Verde Escudo', 'country' => ['CV'], 'numcode' => '132', 'precision' => 2],
        'CZK' => ['name' => 'Czech Koruna', 'country' => ['CZ'], 'numcode' => '203', 'symbol' => 'Kč', 'precision' => 2],
        'DJF' => ['name' => 'Djibouti Franc', 'country' => ['DJ'], 'numcode' => '262', 'precision' => 0],
        'DKK' => ['name' => 'Danish Krone', 'country' => ['DK', 'FO', 'GL'], 'numcode' => '208', 'symbol' => 'kr', 'precision' => 2],
        'DOP' => ['name' => 'Dominican Peso', 'country' => ['DO'], 'numcode' => '214', 'precision' => 2],
        'DZD' => ['name' => 'Algerian Dinar', 'country' => ['DZ'], 'numcode' => '012', 'precision' => 2],
        'EGP' => ['name' => 'Egyptian Pound', 'country' => ['EG'], 'numcode' => '818', 'precision' => 2],
        'ERN' => ['name' => 'Nakfa', 'country' => ['ER'], 'numcode' => '232', 'precision' => 2],
        'ETB' => ['name' => 'Ethiopian Birr', 'country' => ['ET'], 'numcode' => '230', 'precision' => 2],
        'EUR' => ['name' => 'Euro', 'country' => ['ÅLAND ISLANDS', 'AD', 'AT', 'BE', 'CY', 'EE', 'EUROPEAN UNION ', 'FI', 'FR', 'GF', 'FRENCH SOUTHERN TERRITORIES', 'DE', 'GR', 'GP', 'HOLY SEE (VATICAN CITY STATE)', 'IE', 'IT', 'LU', 'MT', 'MQ', 'YT', 'MC', 'MONTENEGRO', 'NL', 'PT', 'RE', 'SAINT MARTIN', 'SAINT PIERRE AND MIQUELON', 'SAINT-BARTHÉLEMY', 'SM', 'SK', 'SI', 'ES', 'Vatican City State (HOLY SEE)'], 'numcode' => '978', 'format' => '%2$s%1$s', 'symbol' => '€', 'precision' => 2, 'dec_point' => '.', 'thousands_sep' => ','],
        'FJD' => ['name' => 'Fiji Dollar', 'country' => ['FIJI'], 'numcode' => '242', 'precision' => 2],
        'FKP' => ['name' => 'Falkland Islands Pound', 'country' => ['FALKLAND ISLANDS (MALVINAS)'], 'numcode' => '238', 'precision' => 2],
        'GBP' => ['name' => 'Pound Sterling', 'country' => ['GUERNSEY', 'ISLE OF MAN', 'JERSEY', 'GB'], 'numcode' => '826', 'format' => '%2$s%1$s', 'symbol' => '£', 'precision' => 2, 'dec_point' => '.', 'thousands_sep' => ','],
        'GEL' => ['name' => 'Lari', 'country' => ['GE'], 'numcode' => '981', 'precision' => 2],
        'GHS' => ['name' => 'Cedi', 'country' => ['GH'], 'numcode' => '936', 'precision' => 2],
        'GIP' => ['name' => 'Gibraltar Pound', 'country' => ['GI'], 'numcode' => '292', 'precision' => 2],
        'GMD' => ['name' => 'Dalasi', 'country' => ['GM'], 'numcode' => '270', 'precision' => 2],
        'GNF' => ['name' => 'Guinea Franc', 'country' => ['GN'], 'numcode' => '324', 'precision' => 0],
        'GTQ' => ['name' => 'Quetzal', 'country' => ['GT'], 'numcode' => '320', 'precision' => 2],
        'GYD' => ['name' => 'Guyana Dollar', 'country' => ['GY'], 'numcode' => '328', 'precision' => 2],
        'HKD' => ['name' => 'Hong Kong Dollar', 'country' => ['HONG KONG'], 'numcode' => '344', 'symbol' => '$', 'precision' => 2],
        'HNL' => ['name' => 'Lempira', 'country' => ['HN'], 'numcode' => '340', 'precision' => 2],
        'HRK' => ['name' => 'Croatian Kuna', 'country' => ['CROATIA'], 'numcode' => '191', 'precision' => 2],
        'HTG' => ['name' => 'Gourde', 'country' => ['HT'], 'numcode' => '332', 'precision' => 2],
        'HUF' => ['name' => 'Forint', 'country' => ['HU'], 'numcode' => '348', 'format' => '%s %s', 'symbol' => 'Ft.', 'precision' => 0, 'dec_point' => ',', 'thousands_sep' => '.'],
        'IDR' => ['name' => 'Rupiah', 'country' => ['ID'], 'numcode' => '360', 'format' => '%2$s%1$s', 'symbol' => 'Rp', 'precision' => 0],
        'ILS' => ['name' => 'New Israeli Sheqel', 'country' => ['IL'], 'numcode' => '376', 'precision' => 2],
        'INR' => ['name' => 'Indian Rupee', 'country' => ['BT', 'IN'], 'numcode' => '356', 'precision' => 2],
        'IQD' => ['name' => 'Iraqi Dinar', 'country' => ['IQ'], 'numcode' => '368', 'precision' => 3],
        'IRR' => ['name' => 'Iranian Rial', 'country' => ['IRAN, ISLAMIC REPUBLIC OF'], 'numcode' => '364', 'precision' => 2],
        'ISK' => ['name' => 'Iceland Krona', 'country' => ['IS'], 'numcode' => '352', 'precision' => 0],
        'JMD' => ['name' => 'Jamaican Dollar', 'country' => ['JM'], 'numcode' => '388', 'precision' => 2],
        'JOD' => ['name' => 'Jordanian Dinar', 'country' => ['JO'], 'numcode' => '400', 'precision' => 3],
        'JPY' => ['name' => 'Yen', 'country' => ['JP'], 'numcode' => '392', 'format' => '%2$s%1$s', 'symbol' => '¥', 'precision' => 0, 'dec_point' => '.', 'thousands_sep' => ','],
        'KES' => ['name' => 'Kenyan Shilling', 'country' => ['KE'], 'numcode' => '404', 'precision' => 2],
        'KGS' => ['name' => 'Som', 'country' => ['KG'], 'numcode' => '417', 'precision' => 2],
        'KHR' => ['name' => 'Riel', 'country' => ['KH'], 'numcode' => '116', 'precision' => 2],
        'KMF' => ['name' => 'Comoro Franc', 'country' => ['KM'], 'numcode' => '174', 'precision' => 0],
        'KPW' => ['name' => 'North Korean Won', 'country' => ['KOREA, DEMOCRATIC PEOPLE’S REPUBLIC OF'], 'numcode' => '408', 'precision' => 2],
        'KRW' => ['name' => 'Won', 'country' => ['KOREA, REPUBLIC OF'], 'numcode' => '410', 'precision' => 0],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'country' => ['KW'], 'numcode' => '414', 'precision' => 3],
        'KYD' => ['name' => 'Cayman Islands Dollar', 'country' => ['KY'], 'numcode' => '136', 'precision' => 2],
        'KZT' => ['name' => 'Tenge', 'country' => ['KZ'], 'numcode' => '398', 'precision' => 2],
        'LAK' => ['name' => 'Kip', 'country' => ['LAO PEOPLE’S DEMOCRATIC REPUBLIC'], 'numcode' => '418', 'precision' => 2],
        'LBP' => ['name' => 'Lebanese Pound', 'country' => ['LB'], 'numcode' => '422', 'precision' => 2],
        'LKR' => ['name' => 'Sri Lanka Rupee', 'country' => ['LK'], 'numcode' => '144', 'precision' => 2],
        'LRD' => ['name' => 'Liberian Dollar', 'country' => ['LR'], 'numcode' => '430', 'precision' => 2],
        'LSL' => ['name' => 'Loti', 'country' => ['LS'], 'numcode' => '426', 'precision' => 2],
        'LTL' => ['name' => 'Lithuanian Litas', 'country' => ['LT'], 'numcode' => '440', 'precision' => 2],
        'LVL' => ['name' => 'Latvian Lats', 'country' => ['LV'], 'numcode' => '428', 'precision' => 2],
        'LYD' => ['name' => 'Libyan Dinar', 'country' => ['LIBYAN ARAB JAMAHIRIYA'], 'numcode' => '434', 'precision' => 3],
        'MAD' => ['name' => 'Moroccan Dirham', 'country' => ['MA', 'WESTERN SAHARA'], 'numcode' => '504', 'precision' => 2],
        'MDL' => ['name' => 'Moldovan Leu', 'country' => ['MOLDOVA, REPUBLIC OF'], 'numcode' => '498', 'precision' => 2],
        'MGA' => ['name' => 'Malagasy Ariary', 'country' => ['MG'], 'numcode' => '969', 'precision' => 2],
        'MKD' => ['name' => 'Denar', 'country' => ['MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF'], 'numcode' => '807', 'precision' => 2],
        'MMK' => ['name' => 'Kyat', 'country' => ['MM'], 'numcode' => '104', 'precision' => 2],
        'MNT' => ['name' => 'Tugrik', 'country' => ['MN'], 'numcode' => '496', 'precision' => 2],
        'MOP' => ['name' => 'Pataca', 'country' => ['MACAO'], 'numcode' => '446', 'precision' => 2],
        'MRO' => ['name' => 'Ouguiya', 'country' => ['MR'], 'numcode' => '478', 'precision' => 2],
        'MUR' => ['name' => 'Mauritius Rupee', 'country' => ['MU'], 'numcode' => '480', 'precision' => 2],
        'MVR' => ['name' => 'Rufiyaa', 'country' => ['MV'], 'numcode' => '462', 'precision' => 2],
        'MWK' => ['name' => 'Kwacha', 'country' => ['MW'], 'numcode' => '454', 'precision' => 2],
        'MXN' => ['name' => 'Mexican Peso', 'country' => ['MX'], 'numcode' => '484', 'symbol' => '$', 'precision' => 2],
        'MXV' => ['name' => 'Mexican Unidad de Inversion (UDI)', 'country' => ['MX'], 'numcode' => '979', 'precision' => 2],
        'MYR' => ['name' => 'Malaysian Ringgit', 'country' => ['MY'], 'numcode' => '458', 'format' => '%2$s %1$s', 'symbol' => 'RM', 'precision' => 2],
        'MZN' => ['name' => 'Metical', 'country' => ['MZ'], 'numcode' => '943', 'precision' => 2],
        'NAD' => ['name' => 'Namibia Dollar', 'country' => ['NA'], 'numcode' => '516', 'precision' => 2],
        'NGN' => ['name' => 'Naira', 'country' => ['NG'], 'numcode' => '566', 'precision' => 2],
        'NIO' => ['name' => 'Cordoba Oro', 'country' => ['NI'], 'numcode' => '558', 'precision' => 2],
        'NOK' => ['name' => 'Norwegian Krone', 'country' => ['BV', 'NO', 'SJ'], 'numcode' => '578', 'symbol' => 'kr', 'precision' => 2],
        'NPR' => ['name' => 'Nepalese Rupee', 'country' => ['NP'], 'numcode' => '524', 'precision' => 2],
        'NZD' => ['name' => 'New Zealand Dollar', 'country' => ['CK', 'NZ', 'NU', 'PITCAIRN', 'TK'], 'numcode' => '554', 'format' => '%2$s%1$s', 'symbol' => '$', 'precision' => 2],
        'OMR' => ['name' => 'Rial Omani', 'country' => ['OM'], 'numcode' => '512', 'precision' => 3],
        'PAB' => ['name' => 'Balboa', 'country' => ['PA'], 'numcode' => '590', 'precision' => 2],
        'PEN' => ['name' => 'Nuevo Sol', 'country' => ['PE'], 'numcode' => '604', 'precision' => 2],
        'PGK' => ['name' => 'Kina', 'country' => ['PG'], 'numcode' => '598', 'precision' => 2],
        'PHP' => ['name' => 'Philippine Peso', 'country' => ['PH'], 'numcode' => '608', 'format' => '%2$s%1$s', 'symbol' => '₱', 'precision' => 2],
        'PKR' => ['name' => 'Pakistan Rupee', 'country' => ['PK'], 'numcode' => '586', 'precision' => 2],
        'PLN' => ['name' => 'Zloty', 'country' => ['PL'], 'numcode' => '985', 'symbol' => 'zł', 'precision' => 2],
        'PYG' => ['name' => 'Guarani', 'country' => ['PY'], 'numcode' => '600', 'precision' => 0],
        'QAR' => ['name' => 'Qatari Rial', 'country' => ['QA'], 'numcode' => '634', 'precision' => 2],
        'RON' => ['name' => 'Leu', 'country' => ['RO'], 'numcode' => '946', 'precision' => 2],
        'RSD' => ['name' => 'Serbian Dinar', 'country' => ['SERBIA '], 'numcode' => '941', 'precision' => 2],
        'RUB' => ['name' => 'Russian Ruble', 'country' => ['RUSSIAN FEDERATION'], 'numcode' => '643', 'precision' => 2, 'symbol' => '₽', 'dec_point' => ',', 'thousands_sep' => ' '],
        'RWF' => ['name' => 'Rwanda Franc', 'country' => ['RW'], 'numcode' => '646', 'precision' => 0],
        'SAR' => ['name' => 'Saudi Riyal', 'country' => ['SA'], 'numcode' => '682', 'precision' => 2],
        'SBD' => ['name' => 'Solomon Islands Dollar', 'country' => ['SB'], 'numcode' => '090', 'precision' => 2],
        'SCR' => ['name' => 'Seychelles Rupee', 'country' => ['SC'], 'numcode' => '690', 'precision' => 2],
        'SDG' => ['name' => 'Sudanese Pound', 'country' => ['SD'], 'numcode' => '938', 'precision' => 2],
        'SEK' => ['name' => 'Swedish Krona', 'country' => ['SE'], 'numcode' => '752', 'symbol' => 'kr', 'precision' => 2],
        'SGD' => ['name' => 'Singapore Dollar', 'country' => ['SG'], 'numcode' => '702', 'symbol' => '$', 'precision' => 2],
        'SHP' => ['name' => 'Saint Helena Pound', 'country' => ['SAINT HELENA, ASCENSION AND TRISTAN DA CUNHA'], 'numcode' => '654', 'precision' => 2],
        'SLL' => ['name' => 'Leone', 'country' => ['SL'], 'numcode' => '694', 'precision' => 2],
        'SOS' => ['name' => 'Somali Shilling', 'country' => ['SO'], 'numcode' => '706', 'precision' => 2],
        'SRD' => ['name' => 'Surinam Dollar', 'country' => ['SR'], 'numcode' => '968', 'precision' => 2],
        'SSP' => ['name' => 'South Sudanese Pound', 'country' => ['SOUTH SUDAN'], 'numcode' => '728', 'precision' => 2],
        'STD' => ['name' => 'Dobra', 'country' => ['ST'], 'numcode' => '678', 'precision' => 2],
        'SVC' => ['name' => 'El Salvador Colon', 'country' => ['SV'], 'numcode' => '222', 'precision' => 2],
        'SYP' => ['name' => 'Syrian Pound', 'country' => ['SYRIAN ARAB REPUBLIC'], 'numcode' => '760', 'precision' => 2],
        'SZL' => ['name' => 'Lilangeni', 'country' => ['SZ'], 'numcode' => '748', 'precision' => 2],
        'THB' => ['name' => 'Baht', 'country' => ['TH'], 'numcode' => '764', 'symbol' => '฿', 'precision' => 2],
        'TJS' => ['name' => 'Somoni', 'country' => ['TJ'], 'numcode' => '972', 'precision' => 2],
        'TMT' => ['name' => 'New Manat', 'country' => ['TM'], 'numcode' => '934', 'precision' => 2],
        'TND' => ['name' => 'Tunisian Dinar', 'country' => ['TN'], 'numcode' => '788', 'precision' => 3],
        'TOP' => ['name' => 'Pa’anga', 'country' => ['TO'], 'numcode' => '776', 'precision' => 2],
        'TRY' => ['name' => 'Turkish Lira', 'country' => ['TR'], 'numcode' => '949', 'symbol' => 'TL', 'precision' => 2],
        'TTD' => ['name' => 'Trinidad and Tobago Dollar', 'country' => ['TT'], 'numcode' => '780', 'precision' => 2],
        'TWD' => ['name' => 'New Taiwan Dollar', 'country' => ['TAIWAN, PROVINCE OF CHINA'], 'numcode' => '901', 'symbol' => '$', 'precision' => 2],
        'TZS' => ['name' => 'Tanzanian Shilling', 'country' => ['TANZANIA, UNITED REPUBLIC OF'], 'numcode' => '834', 'precision' => 2],
        'UAH' => ['name' => 'Hryvnia', 'country' => ['UA'], 'numcode' => '980', 'precision' => 2],
        'UGX' => ['name' => 'Uganda Shilling', 'country' => ['UG'], 'numcode' => '800', 'precision' => 2],
        'USD' => ['name' => 'US Dollar', 'country' => ['AS', 'BONAIRE, SINT EUSTATIUS AND SABA', 'IO', 'EC', 'SV', 'GU', 'HT', 'MH', 'MICRONESIA, FEDERATED STATES OF', 'MP', 'PW', 'PA', 'PR', 'TIMOR-LESTE', 'TC', 'US', 'UM', 'VG', 'VIRGIN ISLANDS (US)'], 'numcode' => '840', 'format' => '%2$s%1$s', 'symbol' => '$', 'precision' => 2, 'dec_point' => '.', 'thousands_sep' => ','],
        'UYI' => ['name' => 'Uruguay Peso en Unidades Indexadas (URUIURUI)', 'country' => ['UY'], 'numcode' => '940', 'precision' => 0],
        'UYU' => ['name' => 'Peso Uruguayo', 'country' => ['UY'], 'numcode' => '858', 'precision' => 2],
        'UZS' => ['name' => 'Uzbekistan Sum', 'country' => ['UZ'], 'numcode' => '860', 'precision' => 2],
        'VEF' => ['name' => 'Bolivar Fuerte', 'country' => ['VENEZUELA, BOLIVARIAN REPUBLIC OF'], 'numcode' => '937', 'precision' => 2],
        'VND' => ['name' => 'Dong', 'country' => ['VN'], 'numcode' => '704', 'precision' => 0],
        'VUV' => ['name' => 'Vatu', 'country' => ['VU'], 'numcode' => '548', 'precision' => 0],
        'WST' => ['name' => 'Tala', 'country' => ['WS'], 'numcode' => '882', 'precision' => 2],
        'XAF' => ['name' => 'CFA Franc BEAC', 'country' => ['CM', 'CF', 'TD', 'CG', 'GA'], 'numcode' => '950', 'precision' => 0],
        'XCD' => ['name' => 'East Caribbean Dollar', 'country' => ['AI', 'AG', 'DM', 'GD', 'MS', 'SAINT KITTS AND NEVIS', 'SAINT LUCIA', 'SAINT VINCENT AND THE GRENADINES'], 'numcode' => '951', 'precision' => 2],
        'XOF' => ['name' => 'CFA Franc BCEAO', 'country' => ['BJ', 'BF', 'CI', 'GW', 'ML', 'NE', 'SN', 'TG'], 'numcode' => '952', 'precision' => 0],
        'XPF' => ['name' => 'CFP Franc', 'country' => ['PF', 'NC', 'WF'], 'numcode' => '953', 'precision' => 0],
        'YER' => ['name' => 'Yemeni Rial', 'country' => ['YE'], 'numcode' => '886', 'precision' => 2],
        'ZAR' => ['name' => 'Rand', 'country' => ['LS', 'NA', 'ZA'], 'numcode' => '710', 'precision' => 2],
        'ZMK' => ['name' => 'Zambian Kwacha', 'country' => ['ZM'], 'numcode' => '894', 'precision' => 2],
        'ZWL' => ['name' => 'Zimbabwe Dollar', 'country' => ['ZW'], 'numcode' => '932', 'precision' => 2],
//        'USN' => array('name' => 'US Dollar (Next day)', 'country' => array('US'), 'numcode' => '997', 'precision' => 2),
//        'USS' => array('name' => 'US Dollar (Same day)', 'country' => array('US'), 'numcode' => '998', 'precision' => 2),
//        'XDR' => array('name' => 'SDR (Special Drawing Right)', 'country' => array('INTERNATIONAL MONETARY FUND (IMF) '), 'numcode' => '960', 'precision' => N.A.),
//        'XSU' => array('name' => 'Sucre', 'country' => array('SISTEMA UNITARIO DE COMPENSACION REGIONAL DE PAGOS SUCRE '), 'numcode' => '994', 'precision' => null),
//        'XUA' => array('name' => 'ADB Unit of Account', 'country' => array('MEMBER COUNTRIES OF THE AFRICAN DEVELOPMENT BANK GROUP'), 'numcode' => '965', 'precision' => null),
    ];
    protected $currency;
    protected $value = "NaN";

    public function __construct($currency = null, $locale = null)
    {
        if (!$currency) $currency = self::getDefault();
        if (!is_string($currency) || strlen($currency)<3)
            throw new Am_Exception_InternalError("Wrong currency code passed");
        $this->currency = $currency;
    }

    static function create($value, $currency = null, $locale = null)
    {
        $c = new self($currency, $locale);
        $c->setValue($value);
        return $c;
    }

    static function render($value, $currency = null, $locale = null)
    {
        return (string)self::create($value, $currency, $locale);
    }

    static function moneyRound($v, $currency = null)
    {
        if (!$currency) $currency = self::getDefault();
        $desc = & self::$currencyList[$currency];
        $precision = isset($desc['precision']) ? $desc['precision'] : 2;

        return floatval(number_format((float)$v, $precision, '.', ''));
    }

    public function setValue($value)
    {
        $this->value = (float)$value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function __toString()
    {
        $desc = & self::$currencyList[$this->currency];

        $format = isset($desc['format']) ? $desc['format'] : '%s %s';
        $symbol = isset($desc['symbol']) ? $desc['symbol'] : $this->currency;
        $precision = isset($desc['precision']) ? $desc['precision'] : 2;
        $dec_point = isset($desc['dec_point']) ? $desc['dec_point'] : '.';
        $thousands_sep = isset($desc['thousands_sep']) ? $desc['thousands_sep'] : ',';

        return sprintf($format,
            number_format((float)$this->value, $precision, $dec_point, $thousands_sep),
            $symbol);
    }

    public function toString()
    {
        return $this->__toString();
    }

    public function equalsTo(Am_Currency $c)
    {
        return $c->currency == $this->currency && $c->value == $this->value;
    }

    static function getSupportedCurrencies($locale=null)
    {
        $ret = [self::getDefault()];
        foreach (Am_Di::getInstance()->plugins_payment->loadEnabled()->getAllEnabled() as $pl){
            if ($list = $pl->getSupportedCurrencies())
                $ret = array_merge($ret, $list);
        }
        $ret = array_unique(array_filter($ret));
        sort($ret);
        return array_combine($ret, $ret);
    }

    /**
     * @return string default currency code
     */
    static public function getDefault()
    {
        return Am_Di::getInstance()->config->get('currency', 'USD');
    }

    static public function getFullList()
    {
        $list = [];
        foreach (self::$currencyList as $code => $p)
            $list[$code] = $code . ' - ' . $p['name'];
        return $list;
    }

    /**
     * Convert 3-letter ISO code to 3-digit numeric code
     * @param type $currency
     * @return type
     */
    static public function getNumericCode($currency)
    {
        if (empty(self::$currencyList[(string)$currency]))
            return null;
        $r = self::$currencyList[(string)$currency];
        return $r['numcode'];
    }
}