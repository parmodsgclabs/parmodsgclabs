<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® GLOBAL KEYWORDS ENGINE
// 100 Countries × Top Languages × 50 Long-tail Patterns
// Product Name + Category = Smart Keywords
// Save to DB + Auto-inject in meta
// ============================================================

// --- 100 COUNTRIES WITH LANGUAGE + CITIES ---
function sgclabs_get_world_countries() {
    return [
        // SOUTH ASIA
        'India'          => ['lang'=>'hi', 'en_name'=>'India',          'cities'=>['Delhi','Mumbai','Bangalore','Chennai','Hyderabad','Kolkata','Pune','Ambala','Ahmedabad','Jaipur','Surat','Lucknow']],
        'Pakistan'       => ['lang'=>'ur', 'en_name'=>'Pakistan',       'cities'=>['Karachi','Lahore','Islamabad','Faisalabad','Rawalpindi','Multan']],
        'Bangladesh'     => ['lang'=>'bn', 'en_name'=>'Bangladesh',     'cities'=>['Dhaka','Chittagong','Sylhet','Khulna','Rajshahi']],
        'Sri Lanka'      => ['lang'=>'si', 'en_name'=>'Sri Lanka',      'cities'=>['Colombo','Kandy','Galle']],
        'Nepal'          => ['lang'=>'ne', 'en_name'=>'Nepal',          'cities'=>['Kathmandu','Pokhara','Lalitpur']],

        // EAST ASIA
        'China'          => ['lang'=>'zh', 'en_name'=>'China',          'cities'=>['Beijing','Shanghai','Guangzhou','Shenzhen','Chengdu','Wuhan','Hangzhou','Nanjing','Tianjin']],
        'Japan'          => ['lang'=>'ja', 'en_name'=>'Japan',          'cities'=>['Tokyo','Osaka','Kyoto','Yokohama','Nagoya','Sapporo','Fukuoka']],
        'South Korea'    => ['lang'=>'ko', 'en_name'=>'South Korea',    'cities'=>['Seoul','Busan','Incheon','Daegu','Daejeon','Gwangju']],
        'Taiwan'         => ['lang'=>'zh', 'en_name'=>'Taiwan',         'cities'=>['Taipei','Kaohsiung','Taichung']],
        'Hong Kong'      => ['lang'=>'zh', 'en_name'=>'Hong Kong',      'cities'=>['Hong Kong','Kowloon','New Territories']],
        'Mongolia'       => ['lang'=>'mn', 'en_name'=>'Mongolia',       'cities'=>['Ulaanbaatar','Erdenet']],

        // SOUTHEAST ASIA
        'Indonesia'      => ['lang'=>'id', 'en_name'=>'Indonesia',      'cities'=>['Jakarta','Surabaya','Bandung','Medan','Semarang','Makassar']],
        'Thailand'       => ['lang'=>'th', 'en_name'=>'Thailand',       'cities'=>['Bangkok','Chiang Mai','Pattaya','Phuket','Hat Yai']],
        'Vietnam'        => ['lang'=>'vi', 'en_name'=>'Vietnam',        'cities'=>['Hanoi','Ho Chi Minh City','Da Nang','Hai Phong','Can Tho']],
        'Philippines'    => ['lang'=>'tl', 'en_name'=>'Philippines',    'cities'=>['Manila','Quezon City','Cebu','Davao','Zamboanga']],
        'Malaysia'       => ['lang'=>'ms', 'en_name'=>'Malaysia',       'cities'=>['Kuala Lumpur','George Town','Ipoh','Shah Alam','Johor Bahru']],
        'Singapore'      => ['lang'=>'en', 'en_name'=>'Singapore',      'cities'=>['Singapore','Jurong','Tampines']],
        'Myanmar'        => ['lang'=>'my', 'en_name'=>'Myanmar',        'cities'=>['Yangon','Mandalay','Naypyidaw']],
        'Cambodia'       => ['lang'=>'km', 'en_name'=>'Cambodia',       'cities'=>['Phnom Penh','Siem Reap','Battambang']],

        // MIDDLE EAST
        'Saudi Arabia'   => ['lang'=>'ar', 'en_name'=>'Saudi Arabia',   'cities'=>['Riyadh','Jeddah','Mecca','Medina','Dammam','Khobar','Tabuk']],
        'UAE'            => ['lang'=>'ar', 'en_name'=>'UAE',             'cities'=>['Dubai','Abu Dhabi','Sharjah','Al Ain','Ajman']],
        'Kuwait'         => ['lang'=>'ar', 'en_name'=>'Kuwait',         'cities'=>['Kuwait City','Hawalli','Salmiya']],
        'Qatar'          => ['lang'=>'ar', 'en_name'=>'Qatar',          'cities'=>['Doha','Al Rayyan','Al Wakrah']],
        'Bahrain'        => ['lang'=>'ar', 'en_name'=>'Bahrain',        'cities'=>['Manama','Riffa','Muharraq']],
        'Oman'           => ['lang'=>'ar', 'en_name'=>'Oman',           'cities'=>['Muscat','Salalah','Sohar']],
        'Jordan'         => ['lang'=>'ar', 'en_name'=>'Jordan',         'cities'=>['Amman','Zarqa','Irbid']],
        'Lebanon'        => ['lang'=>'ar', 'en_name'=>'Lebanon',        'cities'=>['Beirut','Tripoli','Sidon']],
        'Iraq'           => ['lang'=>'ar', 'en_name'=>'Iraq',           'cities'=>['Baghdad','Basra','Mosul','Erbil']],
        'Iran'           => ['lang'=>'fa', 'en_name'=>'Iran',           'cities'=>['Tehran','Mashhad','Isfahan','Tabriz','Shiraz']],
        'Israel'         => ['lang'=>'he', 'en_name'=>'Israel',         'cities'=>['Tel Aviv','Jerusalem','Haifa','Rishon LeZion']],
        'Turkey'         => ['lang'=>'tr', 'en_name'=>'Turkey',         'cities'=>['Istanbul','Ankara','Izmir','Bursa','Adana','Gaziantep']],

        // EUROPE
        'Germany'        => ['lang'=>'de', 'en_name'=>'Germany',        'cities'=>['Berlin','Munich','Hamburg','Frankfurt','Cologne','Stuttgart','Düsseldorf','Leipzig']],
        'France'         => ['lang'=>'fr', 'en_name'=>'France',         'cities'=>['Paris','Marseille','Lyon','Toulouse','Nice','Nantes','Bordeaux','Strasbourg']],
        'UK'             => ['lang'=>'en', 'en_name'=>'UK',             'cities'=>['London','Manchester','Birmingham','Leeds','Glasgow','Liverpool','Bristol','Edinburgh']],
        'Italy'          => ['lang'=>'it', 'en_name'=>'Italy',          'cities'=>['Rome','Milan','Naples','Turin','Palermo','Genoa','Bologna','Florence']],
        'Spain'          => ['lang'=>'es', 'en_name'=>'Spain',          'cities'=>['Madrid','Barcelona','Valencia','Seville','Zaragoza','Málaga','Bilbao']],
        'Netherlands'    => ['lang'=>'nl', 'en_name'=>'Netherlands',    'cities'=>['Amsterdam','Rotterdam','The Hague','Utrecht','Eindhoven','Tilburg']],
        'Belgium'        => ['lang'=>'nl', 'en_name'=>'Belgium',        'cities'=>['Brussels','Antwerp','Ghent','Bruges','Liège']],
        'Switzerland'    => ['lang'=>'de', 'en_name'=>'Switzerland',    'cities'=>['Zurich','Geneva','Basel','Lausanne','Bern']],
        'Austria'        => ['lang'=>'de', 'en_name'=>'Austria',        'cities'=>['Vienna','Graz','Linz','Salzburg','Innsbruck']],
        'Poland'         => ['lang'=>'pl', 'en_name'=>'Poland',         'cities'=>['Warsaw','Kraków','Łódź','Wrocław','Poznań','Gdańsk']],
        'Russia'         => ['lang'=>'ru', 'en_name'=>'Russia',         'cities'=>['Moscow','Saint Petersburg','Novosibirsk','Yekaterinburg','Kazan','Chelyabinsk']],
        'Ukraine'        => ['lang'=>'uk', 'en_name'=>'Ukraine',        'cities'=>['Kyiv','Kharkiv','Odessa','Dnipro','Donetsk','Lviv']],
        'Sweden'         => ['lang'=>'sv', 'en_name'=>'Sweden',         'cities'=>['Stockholm','Gothenburg','Malmö','Uppsala','Västerås']],
        'Norway'         => ['lang'=>'no', 'en_name'=>'Norway',         'cities'=>['Oslo','Bergen','Trondheim','Stavanger']],
        'Denmark'        => ['lang'=>'da', 'en_name'=>'Denmark',        'cities'=>['Copenhagen','Aarhus','Odense','Aalborg']],
        'Finland'        => ['lang'=>'fi', 'en_name'=>'Finland',        'cities'=>['Helsinki','Espoo','Tampere','Vantaa','Oulu']],
        'Portugal'       => ['lang'=>'pt', 'en_name'=>'Portugal',       'cities'=>['Lisbon','Porto','Braga','Coimbra','Funchal']],
        'Greece'         => ['lang'=>'el', 'en_name'=>'Greece',         'cities'=>['Athens','Thessaloniki','Patras','Heraklion','Larissa']],
        'Czech Republic' => ['lang'=>'cs', 'en_name'=>'Czech Republic', 'cities'=>['Prague','Brno','Ostrava','Plzen','Liberec']],
        'Hungary'        => ['lang'=>'hu', 'en_name'=>'Hungary',        'cities'=>['Budapest','Debrecen','Miskolc','Szeged','Pécs']],
        'Romania'        => ['lang'=>'ro', 'en_name'=>'Romania',        'cities'=>['Bucharest','Cluj-Napoca','Timișoara','Iași','Constanța']],

        // AMERICAS
        'USA'            => ['lang'=>'en', 'en_name'=>'USA',            'cities'=>['New York','Los Angeles','Chicago','Houston','Phoenix','Philadelphia','San Antonio','San Diego','Dallas','San Jose','Austin','Seattle']],
        'Canada'         => ['lang'=>'en', 'en_name'=>'Canada',        'cities'=>['Toronto','Montreal','Vancouver','Calgary','Edmonton','Ottawa','Winnipeg','Quebec City']],
        'Brazil'         => ['lang'=>'pt', 'en_name'=>'Brazil',         'cities'=>['São Paulo','Rio de Janeiro','Brasília','Salvador','Fortaleza','Belo Horizonte','Manaus','Curitiba']],
        'Mexico'         => ['lang'=>'es', 'en_name'=>'Mexico',         'cities'=>['Mexico City','Guadalajara','Monterrey','Puebla','Tijuana','León','Juárez','Mérida']],
        'Argentina'      => ['lang'=>'es', 'en_name'=>'Argentina',      'cities'=>['Buenos Aires','Córdoba','Rosario','Mendoza','La Plata','San Miguel']],
        'Colombia'       => ['lang'=>'es', 'en_name'=>'Colombia',       'cities'=>['Bogotá','Medellín','Cali','Barranquilla','Cartagena','Cúcuta']],
        'Chile'          => ['lang'=>'es', 'en_name'=>'Chile',          'cities'=>['Santiago','Valparaíso','Concepción','Antofagasta','Viña del Mar']],
        'Peru'           => ['lang'=>'es', 'en_name'=>'Peru',           'cities'=>['Lima','Arequipa','Trujillo','Chiclayo','Piura']],
        'Venezuela'      => ['lang'=>'es', 'en_name'=>'Venezuela',      'cities'=>['Caracas','Maracaibo','Valencia','Barquisimeto','Ciudad Guayana']],
        'Ecuador'        => ['lang'=>'es', 'en_name'=>'Ecuador',        'cities'=>['Guayaquil','Quito','Cuenca','Santo Domingo']],

        // AFRICA
        'Nigeria'        => ['lang'=>'en', 'en_name'=>'Nigeria',        'cities'=>['Lagos','Kano','Ibadan','Abuja','Port Harcourt','Benin City','Kaduna']],
        'South Africa'   => ['lang'=>'en', 'en_name'=>'South Africa',   'cities'=>['Johannesburg','Cape Town','Durban','Pretoria','Port Elizabeth','Bloemfontein']],
        'Egypt'          => ['lang'=>'ar', 'en_name'=>'Egypt',          'cities'=>['Cairo','Alexandria','Giza','Shubra','Port Said','Suez','Mansoura']],
        'Kenya'          => ['lang'=>'sw', 'en_name'=>'Kenya',          'cities'=>['Nairobi','Mombasa','Kisumu','Nakuru','Eldoret']],
        'Ethiopia'       => ['lang'=>'am', 'en_name'=>'Ethiopia',       'cities'=>['Addis Ababa','Dire Dawa','Mekelle','Gondar','Hawassa']],
        'Tanzania'       => ['lang'=>'sw', 'en_name'=>'Tanzania',       'cities'=>['Dar es Salaam','Mwanza','Arusha','Dodoma','Mbeya']],
        'Ghana'          => ['lang'=>'en', 'en_name'=>'Ghana',          'cities'=>['Accra','Kumasi','Tamale','Sekondi-Takoradi']],
        'Morocco'        => ['lang'=>'ar', 'en_name'=>'Morocco',        'cities'=>['Casablanca','Rabat','Fez','Marrakesh','Agadir','Tangier']],
        'Algeria'        => ['lang'=>'ar', 'en_name'=>'Algeria',        'cities'=>['Algiers','Oran','Constantine','Annaba','Blida']],
        'Tunisia'        => ['lang'=>'ar', 'en_name'=>'Tunisia',        'cities'=>['Tunis','Sfax','Sousse','Kairouan','Bizerte']],
        'Sudan'          => ['lang'=>'ar', 'en_name'=>'Sudan',          'cities'=>['Khartoum','Omdurman','Port Sudan','Kassala']],
        'Libya'          => ['lang'=>'ar', 'en_name'=>'Libya',          'cities'=>['Tripoli','Benghazi','Misrata','Tarhuna']],
        'Uganda'         => ['lang'=>'en', 'en_name'=>'Uganda',         'cities'=>['Kampala','Gulu','Lira','Mbarara']],
        'Zimbabwe'       => ['lang'=>'en', 'en_name'=>'Zimbabwe',       'cities'=>['Harare','Bulawayo','Chitungwiza','Mutare']],

        // OCEANIA
        'Australia'      => ['lang'=>'en', 'en_name'=>'Australia',      'cities'=>['Sydney','Melbourne','Brisbane','Perth','Adelaide','Canberra','Gold Coast','Newcastle']],
        'New Zealand'    => ['lang'=>'en', 'en_name'=>'New Zealand',    'cities'=>['Auckland','Wellington','Christchurch','Hamilton','Tauranga']],

        // CENTRAL ASIA
        'Kazakhstan'     => ['lang'=>'kk', 'en_name'=>'Kazakhstan',     'cities'=>['Almaty','Nur-Sultan','Shymkent','Karaganda','Aktobe']],
        'Uzbekistan'     => ['lang'=>'uz', 'en_name'=>'Uzbekistan',     'cities'=>['Tashkent','Samarkand','Namangan','Andijan','Nukus']],
        'Azerbaijan'     => ['lang'=>'az', 'en_name'=>'Azerbaijan',     'cities'=>['Baku','Ganja','Sumqayit','Mingachevir']],
        'Georgia'        => ['lang'=>'ka', 'en_name'=>'Georgia',        'cities'=>['Tbilisi','Batumi','Kutaisi','Rustavi']],
        'Armenia'        => ['lang'=>'hy', 'en_name'=>'Armenia',        'cities'=>['Yerevan','Gyumri','Vanadzor']],

        // ADDITIONAL
        'Ireland'        => ['lang'=>'en', 'en_name'=>'Ireland',        'cities'=>['Dublin','Cork','Limerick','Galway','Waterford']],
        'South Sudan'    => ['lang'=>'en', 'en_name'=>'South Sudan',    'cities'=>['Juba','Wau','Malakal']],
        'Afghanistan'    => ['lang'=>'ps', 'en_name'=>'Afghanistan',    'cities'=>['Kabul','Kandahar','Herat','Mazar-i-Sharif']],
        'Myanmar'        => ['lang'=>'my', 'en_name'=>'Myanmar',        'cities'=>['Yangon','Mandalay','Naypyidaw']],
        'Laos'           => ['lang'=>'lo', 'en_name'=>'Laos',           'cities'=>['Vientiane','Luang Prabang','Savannakhet']],
        'Cuba'           => ['lang'=>'es', 'en_name'=>'Cuba',           'cities'=>['Havana','Santiago de Cuba','Camagüey','Holguín']],
        'Dominican Rep.' => ['lang'=>'es', 'en_name'=>'Dominican Republic', 'cities'=>['Santo Domingo','Santiago','La Romana']],
        'Guatemala'      => ['lang'=>'es', 'en_name'=>'Guatemala',      'cities'=>['Guatemala City','Mixco','Villa Nueva','Quetzaltenango']],
        'Bolivia'        => ['lang'=>'es', 'en_name'=>'Bolivia',        'cities'=>['Santa Cruz','La Paz','Cochabamba','Sucre']],
        'Paraguay'       => ['lang'=>'es', 'en_name'=>'Paraguay',       'cities'=>['Asunción','Ciudad del Este','San Lorenzo']],
        'Uruguay'        => ['lang'=>'es', 'en_name'=>'Uruguay',        'cities'=>['Montevideo','Salto','Ciudad de la Costa']],
        'Cameroon'       => ['lang'=>'fr', 'en_name'=>'Cameroon',       'cities'=>['Douala','Yaoundé','Bamenda','Bafoussam']],
        'Ivory Coast'    => ['lang'=>'fr', 'en_name'=>'Ivory Coast',    'cities'=>["Abidjan","Bouaké","Daloa","Yamoussoukro"]],
        'Senegal'        => ['lang'=>'fr', 'en_name'=>'Senegal',        'cities'=>['Dakar','Touba','Thiès','Kaolack']],
        'Serbia'         => ['lang'=>'sr', 'en_name'=>'Serbia',         'cities'=>['Belgrade','Novi Sad','Niš','Kragujevac']],
        'Croatia'        => ['lang'=>'hr', 'en_name'=>'Croatia',        'cities'=>['Zagreb','Split','Rijeka','Osijek']],
        'Slovakia'       => ['lang'=>'sk', 'en_name'=>'Slovakia',       'cities'=>['Bratislava','Košice','Prešov','Žilina']],
        'Bulgaria'       => ['lang'=>'bg', 'en_name'=>'Bulgaria',       'cities'=>['Sofia','Plovdiv','Varna','Burgas','Ruse']],
    ];
}

// --- 50 LONG-TAIL KEYWORD PATTERNS ---
function sgclabs_get_longtail_patterns_global() {
    return [
        // Buy intent (high conversion)
        'buy {product} online {country}',
        'buy {product} in {city}',
        '{product} price in {country}',
        '{product} price in {city}',
        '{product} for sale {country}',
        'order {product} from India to {country}',
        'cheap {product} {country}',
        'affordable {product} {city}',
        '{product} best price {country}',
        '{product} discount {country}',

        // Supplier/Manufacturer intent
        '{product} supplier in {country}',
        '{product} supplier in {city}',
        '{product} manufacturer {country}',
        '{product} manufacturer India export to {country}',
        '{product} exporter to {country}',
        '{product} wholesaler {country}',
        '{product} wholesale price {city}',
        '{product} distributor {country}',
        '{product} dealer in {city}',
        'best {product} supplier {country}',

        // Quality/Specification intent
        'borosilicate {product} {country}',
        'laboratory grade {product} {city}',
        'high quality {product} {country}',
        'premium {product} supplier {country}',
        'ISO certified {product} {country}',
        '{product} grade A {country}',
        'scientific {product} {country}',
        'lab grade {product} {city}',
        '{product} specifications {country}',
        '{product} standard size {country}',

        // Category/Industry intent
        'laboratory {product} {country}',
        'laboratory {product} {city}',
        'scientific equipment {product} {country}',
        'chemistry lab {product} {country}',
        'research grade {product} {country}',
        'analytical grade {product} {city}',
        'glass {product} laboratory {country}',
        '{product} for chemistry lab {country}',
        '{product} for research lab {city}',
        '{product} for university lab {country}',

        // Import/Export intent
        'import {product} from India {country}',
        '{product} import {city}',
        '{product} India manufacturer {country}',
        'SGC LABS {product} {country}',
        'India made {product} {country}',
        'made in India {product} {city}',
        '{product} Ambala India {country}',

        // Long-tail comparison/info intent
        '{product} OEM {country}',
        '{product} custom print {country}',
        'bulk {product} order {country}',
    ];
}

// --- LANGUAGE TRANSLATIONS FOR KEYWORDS ---
function sgclabs_get_language_translations() {
    return [
        'en' => ['buy'=>'buy','supplier'=>'supplier','manufacturer'=>'manufacturer','price'=>'price','laboratory'=>'laboratory','quality'=>'quality','wholesale'=>'wholesale','from'=>'from','in'=>'in','for'=>'for','best'=>'best','cheap'=>'cheap','online'=>'online','import'=>'import','order'=>'order'],
        'hi' => ['buy'=>'खरीदें','supplier'=>'आपूर्तिकर्ता','manufacturer'=>'निर्माता','price'=>'मूल्य','laboratory'=>'प्रयोगशाला','quality'=>'गुणवत्ता','wholesale'=>'थोक','from'=>'से','in'=>'में','for'=>'के लिए','best'=>'सर्वश्रेष्ठ','cheap'=>'सस्ता','online'=>'ऑनलाइन','import'=>'आयात','order'=>'ऑर्डर'],
        'ar' => ['buy'=>'اشتري','supplier'=>'مورد','manufacturer'=>'مصنّع','price'=>'السعر','laboratory'=>'مختبر','quality'=>'جودة','wholesale'=>'بالجملة','from'=>'من','in'=>'في','for'=>'لـ','best'=>'أفضل','cheap'=>'رخيص','online'=>'عبر الإنترنت','import'=>'استيراد','order'=>'اطلب'],
        'de' => ['buy'=>'kaufen','supplier'=>'Lieferant','manufacturer'=>'Hersteller','price'=>'Preis','laboratory'=>'Labor','quality'=>'Qualität','wholesale'=>'Großhandel','from'=>'aus','in'=>'in','for'=>'für','best'=>'beste','cheap'=>'günstig','online'=>'online','import'=>'importieren','order'=>'bestellen'],
        'fr' => ['buy'=>'acheter','supplier'=>'fournisseur','manufacturer'=>'fabricant','price'=>'prix','laboratory'=>'laboratoire','quality'=>'qualité','wholesale'=>'gros','from'=>'de','in'=>'à','for'=>'pour','best'=>'meilleur','cheap'=>'pas cher','online'=>'en ligne','import'=>'importer','order'=>'commander'],
        'es' => ['buy'=>'comprar','supplier'=>'proveedor','manufacturer'=>'fabricante','price'=>'precio','laboratory'=>'laboratorio','quality'=>'calidad','wholesale'=>'mayoreo','from'=>'de','in'=>'en','for'=>'para','best'=>'mejor','cheap'=>'barato','online'=>'en línea','import'=>'importar','order'=>'pedir'],
        'zh' => ['buy'=>'购买','supplier'=>'供应商','manufacturer'=>'制造商','price'=>'价格','laboratory'=>'实验室','quality'=>'质量','wholesale'=>'批发','from'=>'来自','in'=>'在','for'=>'用于','best'=>'最好','cheap'=>'便宜','online'=>'在线','import'=>'进口','order'=>'订购'],
        'ja' => ['buy'=>'購入','supplier'=>'サプライヤー','manufacturer'=>'製造業者','price'=>'価格','laboratory'=>'実験室','quality'=>'品質','wholesale'=>'卸売り','from'=>'から','in'=>'で','for'=>'のための','best'=>'最高','cheap'=>'安い','online'=>'オンライン','import'=>'輸入','order'=>'注文'],
        'ko' => ['buy'=>'구매','supplier'=>'공급업체','manufacturer'=>'제조업체','price'=>'가격','laboratory'=>'실험실','quality'=>'품질','wholesale'=>'도매','from'=>'에서','in'=>'에','for'=>'위한','best'=>'최고','cheap'=>'저렴한','online'=>'온라인','import'=>'수입','order'=>'주문'],
        'pt' => ['buy'=>'comprar','supplier'=>'fornecedor','manufacturer'=>'fabricante','price'=>'preço','laboratory'=>'laboratório','quality'=>'qualidade','wholesale'=>'atacado','from'=>'de','in'=>'em','for'=>'para','best'=>'melhor','cheap'=>'barato','online'=>'online','import'=>'importar','order'=>'pedir'],
        'ru' => ['buy'=>'купить','supplier'=>'поставщик','manufacturer'=>'производитель','price'=>'цена','laboratory'=>'лаборатория','quality'=>'качество','wholesale'=>'оптом','from'=>'из','in'=>'в','for'=>'для','best'=>'лучший','cheap'=>'дешёвый','online'=>'онлайн','import'=>'импорт','order'=>'заказать'],
        'id' => ['buy'=>'beli','supplier'=>'pemasok','manufacturer'=>'produsen','price'=>'harga','laboratory'=>'laboratorium','quality'=>'kualitas','wholesale'=>'grosir','from'=>'dari','in'=>'di','for'=>'untuk','best'=>'terbaik','cheap'=>'murah','online'=>'online','import'=>'impor','order'=>'pesan'],
        'tr' => ['buy'=>'satın al','supplier'=>'tedarikçi','manufacturer'=>'üretici','price'=>'fiyat','laboratory'=>'laboratuvar','quality'=>'kalite','wholesale'=>'toptan','from'=>'den','in'=>'de','for'=>'için','best'=>'en iyi','cheap'=>'ucuz','online'=>'çevrimiçi','import'=>'ithalat','order'=>'sipariş'],
        'vi' => ['buy'=>'mua','supplier'=>'nhà cung cấp','manufacturer'=>'nhà sản xuất','price'=>'giá','laboratory'=>'phòng thí nghiệm','quality'=>'chất lượng','wholesale'=>'bán buôn','from'=>'từ','in'=>'tại','for'=>'cho','best'=>'tốt nhất','cheap'=>'rẻ','online'=>'trực tuyến','import'=>'nhập khẩu','order'=>'đặt hàng'],
        'th' => ['buy'=>'ซื้อ','supplier'=>'ผู้จัดจำหน่าย','manufacturer'=>'ผู้ผลิต','price'=>'ราคา','laboratory'=>'ห้องปฏิบัติการ','quality'=>'คุณภาพ','wholesale'=>'ขายส่ง','from'=>'จาก','in'=>'ใน','for'=>'สำหรับ','best'=>'ดีที่สุด','cheap'=>'ราคาถูก','online'=>'ออนไลน์','import'=>'นำเข้า','order'=>'สั่งซื้อ'],
        'it' => ['buy'=>'comprare','supplier'=>'fornitore','manufacturer'=>'produttore','price'=>'prezzo','laboratory'=>'laboratorio','quality'=>'qualità','wholesale'=>'all\'ingrosso','from'=>'da','in'=>'in','for'=>'per','best'=>'migliore','cheap'=>'economico','online'=>'online','import'=>'importare','order'=>'ordinare'],
        'nl' => ['buy'=>'kopen','supplier'=>'leverancier','manufacturer'=>'fabrikant','price'=>'prijs','laboratory'=>'laboratorium','quality'=>'kwaliteit','wholesale'=>'groothandel','from'=>'van','in'=>'in','for'=>'voor','best'=>'beste','cheap'=>'goedkoop','online'=>'online','import'=>'importeren','order'=>'bestellen'],
        'pl' => ['buy'=>'kupić','supplier'=>'dostawca','manufacturer'=>'producent','price'=>'cena','laboratory'=>'laboratorium','quality'=>'jakość','wholesale'=>'hurtowo','from'=>'z','in'=>'w','for'=>'dla','best'=>'najlepszy','cheap'=>'tani','online'=>'online','import'=>'import','order'=>'zamówić'],
        'fa' => ['buy'=>'خرید','supplier'=>'تامین‌کننده','manufacturer'=>'سازنده','price'=>'قیمت','laboratory'=>'آزمایشگاه','quality'=>'کیفیت','wholesale'=>'عمده','from'=>'از','in'=>'در','for'=>'برای','best'=>'بهترین','cheap'=>'ارزان','online'=>'آنلاین','import'=>'واردات','order'=>'سفارش'],
        'uk' => ['buy'=>'купити','supplier'=>'постачальник','manufacturer'=>'виробник','price'=>'ціна','laboratory'=>'лабораторія','quality'=>'якість','wholesale'=>'оптом','from'=>'з','in'=>'в','for'=>'для','best'=>'кращий','cheap'=>'дешевий','online'=>'онлайн','import'=>'імпорт','order'=>'замовити'],
        'ms' => ['buy'=>'beli','supplier'=>'pembekal','manufacturer'=>'pengilang','price'=>'harga','laboratory'=>'makmal','quality'=>'kualiti','wholesale'=>'borong','from'=>'dari','in'=>'di','for'=>'untuk','best'=>'terbaik','cheap'=>'murah','online'=>'dalam talian','import'=>'import','order'=>'pesan'],
    ];
}

// --- GENERATE SMART KEYWORDS FOR A PRODUCT ---
function sgclabs_generate_global_keywords($product_id) {
    if (!function_exists('wc_get_product')) return [];
    $product = wc_get_product($product_id);
    if (!$product) return [];

    $name      = $product->get_name();
    $cats      = wp_get_post_terms($product_id, 'product_cat', ['fields'=>'names']);
    $cat       = !empty($cats) ? $cats[0] : 'laboratory equipment';
    $countries = sgclabs_get_world_countries();
    $patterns  = sgclabs_get_longtail_patterns_global();
    $langs     = sgclabs_get_language_translations();

    $all_keywords = [];

    foreach ($countries as $country_name => $country_data) {
        $lang_code = $country_data['lang'];
        $cities    = $country_data['cities'];
        $trans     = $langs[$lang_code] ?? $langs['en'];

        // Pick top 3 cities per country to avoid overload
        $top_cities = array_slice($cities, 0, 3);

        foreach ($patterns as $pattern) {
            // Replace {product} with actual product name
            $kw = str_replace('{product}', $name, $pattern);

            // Version with country only
            $kw_country = str_replace(
                ['{country}', '{city}'],
                [$country_name, $country_name],
                $kw
            );
            $all_keywords[] = $kw_country;

            // Version with top city
            foreach ($top_cities as $city) {
                $kw_city = str_replace(
                    ['{country}', '{city}'],
                    [$country_name, $city],
                    $kw
                );
                if ($kw_city !== $kw_country) {
                    $all_keywords[] = $kw_city;
                }
                break; // Only 1 city per pattern to keep manageable
            }

            // Native language version (translate action words)
            if ($lang_code !== 'en') {
                $native_kw = $pattern;
                foreach ($trans as $en_word => $native_word) {
                    $native_kw = str_replace($en_word, $native_word, $native_kw);
                }
                $native_kw = str_replace('{product}', $name, $native_kw);
                $native_kw = str_replace(['{country}','{city}'], [$country_name, $top_cities[0]??$country_name], $native_kw);
                $all_keywords[] = $native_kw;
            }
        }

        // Category-specific keywords per country
        $all_keywords[] = "{$cat} supplier {$country_name}";
        $all_keywords[] = "{$cat} manufacturer India export {$country_name}";
        $all_keywords[] = "buy {$cat} {$country_name}";
    }

    // Remove duplicates + clean
    $clean = array_unique(array_filter(array_map('trim', $all_keywords)));
    return array_values($clean);
}

// --- SAVE KEYWORDS TO DATABASE ---
function sgclabs_save_product_global_keywords($product_id) {
    global $wpdb;
    $keywords = sgclabs_generate_global_keywords($product_id);
    if (empty($keywords)) return 0;

    // Save as post meta (JSON chunks — MySQL TEXT limit safe)
    $chunks = array_chunk($keywords, 500);
    delete_post_meta($product_id, '_sgclabs_global_kw');
    foreach ($chunks as $i => $chunk) {
        add_post_meta($product_id, '_sgclabs_global_kw', wp_json_encode($chunk, JSON_UNESCAPED_UNICODE));
    }
    update_post_meta($product_id, '_sgclabs_global_kw_count', count($keywords));
    update_post_meta($product_id, '_sgclabs_global_kw_time', time());

    return count($keywords);
}

// --- GET SAVED KEYWORDS FOR A PRODUCT ---
function sgclabs_get_product_global_keywords($product_id) {
    $chunks = get_post_meta($product_id, '_sgclabs_global_kw');
    if (empty($chunks)) return [];
    $all = [];
    foreach ($chunks as $chunk) {
        $decoded = json_decode($chunk, true);
        if (is_array($decoded)) $all = array_merge($all, $decoded);
    }
    return $all;
}

// --- AUTO-INJECT ON PRODUCT PAGES ---
add_action('wp_head', 'sgclabs_inject_global_keywords_meta', 7);
function sgclabs_inject_global_keywords_meta(){
    // Keywords meta — low conflict risk but skip if SEO plugin active
    if ( defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') ) return;
    if (get_option('sgclabs_global_keywords_active', '1') !== '1') return;
    if (!function_exists('is_product') || !is_product()) return;
    $pid   = get_the_ID();
    $count = get_post_meta($pid, '_sgclabs_global_kw_count', true);
    if (!$count) return;

    // Get visitor country from Accept-Language header
    $lang_header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
    $visitor_lang = substr($lang_header, 0, 2);

    // Get all keywords
    $all_kws = sgclabs_get_product_global_keywords($pid);
    if (empty($all_kws)) return;

    // Filter keywords relevant to visitor's language (first 30)
    $relevant = array_filter($all_kws, function($kw) use ($visitor_lang) {
        // Always include English keywords
        if (preg_match('/^[a-zA-Z0-9\s\-,\.]+$/', $kw)) return true;
        return true; // Include all for now
    });

    $meta_kws = array_slice(array_values($relevant), 0, 30);
    if (!empty($meta_kws)) {
        echo "<meta name='keywords' content='" . esc_attr(implode(', ', $meta_kws)) . "'>\n";
        echo "<!-- SGC LABS® Global Keywords: {$count} total, 30 injected for lang:{$visitor_lang} -->\n";
    }
}

// --- BULK GENERATE AJAX ---
add_action('wp_ajax_sgclabs_bulk_global_kw', 'sgclabs_bulk_global_kw_ajax');
function sgclabs_bulk_global_kw_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    if (!function_exists('wc_get_products')) { wp_send_json_error('WooCommerce not active'); return; }

    // Max performance
    @ini_set('memory_limit', '1024M');
    @ini_set('max_execution_time', '300');
    @set_time_limit(300);

    $offset = intval($_POST['offset'] ?? 0);
    $batch  = 10; // Increased from 3 → 10 for 3x speed

    // Direct DB query — much faster than wc_get_products
    global $wpdb;
    $product_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type='product' AND post_status='publish'
         ORDER BY ID ASC LIMIT %d OFFSET %d",
        $batch, $offset
    ));
    $total = (int)$wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    $done     = 0;
    $total_kw = 0;

    foreach ($product_ids as $pid) {
        $count = sgclabs_save_product_global_keywords((int)$pid);
        $total_kw += $count;
        $done++;
    }

    $processed = $offset + $done;
    wp_send_json_success([
        'processed'     => $processed,
        'total'         => $total,
        'percent'       => min(100, round($processed / max($total,1) * 100)),
        'done'          => count($product_ids) < $batch,
        'next_offset'   => $offset + $batch,
        'kw_this_batch' => $total_kw,
    ]);
}

// --- PREVIEW AJAX ---
add_action('wp_ajax_sgclabs_preview_global_kw', 'sgclabs_preview_global_kw_ajax');
function sgclabs_preview_global_kw_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    $pid     = intval($_POST['product_id'] ?? 0);
    $country = sanitize_text_field($_POST['country'] ?? '');
    $all_kws = sgclabs_get_product_global_keywords($pid);
    // Filter by country if specified
    if ($country) {
        $filtered = array_filter($all_kws, fn($k) => stripos((string)$k, $country) !== false);
        $all_kws  = array_values($filtered);
    }
    wp_send_json_success([
        'keywords' => array_slice($all_kws, 0, 100),
        'total'    => get_post_meta($pid, '_sgclabs_global_kw_count', true) ?: 0,
    ]);
}

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_global_keywords_page() {
    global $wpdb;
    $total      = wp_count_posts('product')->publish ?? 0;
    $generated  = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_global_kw_count'") ?? 0;
    $pct        = $total > 0 ? round($generated / $total * 100) : 0;
    $countries  = sgclabs_get_world_countries();
    $patterns   = sgclabs_get_longtail_patterns_global();
    $langs      = sgclabs_get_language_translations();
    $recent_pids = function_exists('wc_get_products') ? wc_get_products(['limit'=>6,'status'=>'publish','return'=>'ids']) : [];
    ?>
    <div class="wrap sgclabs-wrap">
<?php sgclabs_module_control_bar(["module_id"=>"global-keywords","module_name"=>"Global Keywords Engine","module_icon"=>"🌍","option_key"=>"sgclabs_global_keywords_active","default"=>"1","description"=>"Auto-inject geo-targeted keywords into product meta","modes"=>[["value"=>"ai","label"=>"AI + GSC","desc"=>"AI generates keywords using GSC data"],["value"=>"manual","label"=>"Manual Keywords","desc"=>"Use only manually added keywords"],["value"=>"gsc","label"=>"GSC Only","desc"=>"Import from Google Search Console"]],"mode_key"=>"sgclabs_gkw_mode","mode_default"=>"ai"]); ?>
        <h1>🌍 Global Keywords Engine</h1>
        <p><strong><?php echo count($countries); ?> Countries</strong> × <strong><?php echo count($langs); ?> Languages</strong> × <strong><?php echo count($patterns); ?> Long-tail Patterns</strong> = Har product ke liye <strong>~<?php echo number_format(count($countries) * count($patterns) * 2); ?>+ keywords!</strong></p>

        <!-- Stats -->
        <div class="sgclabs-seo-stats">
            <div class="seo-stat-box">
                <div class="seo-stat-num"><?php echo count($countries); ?></div>
                <div class="seo-stat-label">Countries</div>
            </div>
            <div class="seo-stat-box blue">
                <div class="seo-stat-num"><?php echo count($langs); ?></div>
                <div class="seo-stat-label">Languages</div>
            </div>
            <div class="seo-stat-box orange">
                <div class="seo-stat-num"><?php echo count($patterns); ?></div>
                <div class="seo-stat-label">Patterns</div>
            </div>
            <div class="seo-stat-box green">
                <div class="seo-stat-num"><?php echo intval($pct); ?>%</div>
                <div class="seo-stat-label">Generated</div>
            </div>
        </div>

        <!-- Progress -->
        <div class="sgclabs-section">
            <div class="seo-progress-outer">
                <div class="seo-progress-inner" style="width:<?php echo intval($pct); ?>%"><?php echo intval($pct); ?>%</div>
            </div>
            <p><?php echo number_format($generated); ?> / <?php echo number_format($total); ?> products processed</p>
        </div>

        <!-- Bulk Generator -->
        <div class="sgclabs-section">
            <h2>🚀 Bulk Global Keywords Generator</h2>
            <div class="sgclabs-ok-box" style="margin-bottom:15px;">
                💡 <strong>Har product ke liye ~<?php echo number_format(count($countries)*count($patterns)*2); ?>+ keywords</strong> generate honge. <strong>10 products per batch — 3x faster!</strong>
            </div>
            <div style="display:flex;gap:15px;flex-wrap:wrap;margin-bottom:15px;">
                <div id="sgc-pnav-globalkw"></div>
                <button class="button button-large" onclick="clearGKWCache()">🗑️ Clear & Regenerate</button>
            </div>
            <div id="gkw-progress">
                <div class="seo-progress-outer">
                    <div id="gkw-fill" class="seo-progress-inner" style="width:<?php echo intval($pct); ?>%"><?php echo intval($pct); ?>%</div>
                </div>
                <p id="gkw-status" style="margin-top:8px;font-weight:600;">
                    <?php if ($pct > 0 && $pct < 100): ?>
                        ⏸️ Previous run: <?php echo intval($generated); ?>/<?php echo intval($total); ?>.
                        <button class="button button-primary" onclick="resumeGKWBulk()">▶️ Resume</button>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Preview -->
        <div class="sgclabs-section">
            <h2>👁️ Preview Keywords for a Product</h2>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;">
                <select id="gkw-product" style="min-width:250px;padding:8px;">
                    <option value="">-- Select Product --</option>
                    <?php foreach ($recent_pids as $pid):
                        if (!function_exists('wc_get_product')) break;
                        $p = wc_get_product($pid); if (!$p) continue;
                        $kw_count = get_post_meta($pid, '_sgclabs_global_kw_count', true);
                    ?>
                        <option value="<?php echo intval($pid); ?>">
                            <?php echo esc_html($p->get_name()); ?>
                            <?php if ($kw_count): ?>(<?php echo number_format($kw_count); ?> kw)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="gkw-country-filter" style="padding:8px;min-width:180px;">
                    <option value="">All Countries</option>
                    <?php foreach ($countries as $cn => $cd): ?>
                        <option value="<?php echo esc_attr($cn); ?>"><?php echo esc_html($cn); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-primary" onclick="previewGKW()">🔍 Preview Keywords</button>
            </div>
            <div id="gkw-preview" style="display:none;">
                <p id="gkw-preview-count" style="font-weight:bold;color:#1a1a2e;"></p>
                <div id="gkw-preview-tags" class="kw-tag-cloud" style="max-height:300px;overflow-y:auto;"></div>
            </div>
        </div>

        <!-- Countries Overview -->
        <div class="sgclabs-section">
            <h2>🌏 Countries Covered</h2>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach ($countries as $cn => $cd): ?>
                    <span class="sgclabs-tag" style="font-size:11px;">
                        <?php echo esc_html($cn); ?>
                        <small style="color:#888;">(<?php echo strtoupper($cd['lang']); ?>)</small>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Patterns Overview -->
        <div class="sgclabs-section">
            <h2>🔑 50 Long-tail Patterns</h2>
            <p class="description"><code>{product}</code> = product name, <code>{country}</code> = country name, <code>{city}</code> = city name</p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach ($patterns as $i => $p): ?>
                    <span class="sgclabs-tag" style="background:#f0f4ff;">
                        <?php echo ($i+1); ?>. <?php echo esc_html($p); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Languages -->
        <div class="sgclabs-section">
            <h2>🗣️ Languages with Native Keywords</h2>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php
                $lang_names = ['en'=>'🇬🇧 English','hi'=>'🇮🇳 Hindi','ar'=>'🇸🇦 Arabic','de'=>'🇩🇪 German','fr'=>'🇫🇷 French','es'=>'🇪🇸 Spanish','zh'=>'🇨🇳 Chinese','ja'=>'🇯🇵 Japanese','ko'=>'🇰🇷 Korean','pt'=>'🇧🇷 Portuguese','ru'=>'🇷🇺 Russian','id'=>'🇮🇩 Indonesian','tr'=>'🇹🇷 Turkish','vi'=>'🇻🇳 Vietnamese','th'=>'🇹🇭 Thai','it'=>'🇮🇹 Italian','nl'=>'🇳🇱 Dutch','pl'=>'🇵🇱 Polish','fa'=>'🇮🇷 Persian','uk'=>'🇺🇦 Ukrainian','ms'=>'🇲🇾 Malay'];
                foreach ($lang_names as $code => $name) {
                    echo "<span class='sgclabs-tag' style='font-size:13px;padding:6px 14px;'>{$name}</span>";
                }
                ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
    if (typeof SgcProgressNav === 'undefined') return;
    // ── Global Keywords Progress Nav ────────────────────────────
    const gkwNav = new SgcProgressNav('sgc-pnav-globalkw', {
        label: 'Global Keywords Engine',
        icon: '🌍',
        showSpeed: false,
        counters: [{id:'sgc-gkw-done', label:'Applied'}, {id:'sgc-gkw-total', label:'Products'}],
        onPlay:  function(nav) { gkwOffset = 0; runGKW(nav); },
        onPause: function(nav) { gkwPaused = true; },
        onStop:  function(nav) { gkwPaused = true; gkwOffset = 0; },
        onReset: function(nav) { gkwPaused = true; gkwOffset = 0; }
    });
    let gkwOffset = 0, gkwPaused = false;

    function runGKW(nav) {
        if (nav.isPaused() || nav.isStopped()) return;
        const nonce = '<?php echo esc_js( wp_create_nonce( "sgclabs_nonce" ) ); ?>';
        jQuery.post(ajaxurl, {
            action: 'sgclabs_bulk_global_kw', nonce: nonce, offset: gkwOffset
        }, function(res) {
            if (!res.success) { nav.error(res.data || 'Error'); return; }
            const d = res.data;
            gkwOffset = d.next_offset || 0;
            const pct = d.total > 0 ? Math.round(d.processed / d.total * 100) : 100;
            nav.setPercent(pct, d.processed, d.total);
            nav.setCounter('sgc-gkw-done',  d.processed);
            nav.setCounter('sgc-gkw-total', d.total);
            nav.status('<span class="sgc-pulse"></span> ' + d.processed + ' / ' + d.total + ' products');
            if (d.done || pct >= 100) { nav.done('Keywords applied to ' + d.total + ' products!'); }
            else { setTimeout(function(){ runGKW(nav); }, 150); }
        }).fail(function(){ nav.error('Network error.'); });
    }

    const nonce = '<?php echo esc_js( wp_create_nonce( 'sgclabs_nonce' ) ); ?>';
    let gkwRunning = false, gkwErrors = 0;
    const gkwTotal = <?php echo intval($total); ?>;

    window.addEventListener('load', function() {
        const saved = localStorage.getItem('sgclabs_gkw_progress');
        if (saved) {
            try {
                const p = JSON.parse(saved);
                if (p.total === gkwTotal && p.offset > 0 && p.offset < p.total) {
                    const pct = Math.round(p.offset/p.total*100);
                    document.getElementById('gkw-fill').style.width = pct+'%';
                    document.getElementById('gkw-fill').textContent = pct+'%';
                    document.getElementById('gkw-status').innerHTML =
                        '⏸️ Ruka tha: <strong>'+p.offset+'/'+p.total+'</strong> &nbsp;'
                        +'<button class="button button-primary" onclick="resumeGKWBulk()">▶️ Resume</button> &nbsp;'
                        +'<button class="button" onclick="startGKWBulk()">🔄 Restart</button>';
                }
            } catch(e) {}
        }
    });

    function startGKWBulk() {
        localStorage.removeItem('sgclabs_gkw_progress');
        gkwOffset = 0; gkwErrors = 0; gkwRunning = true;
        document.getElementById('gkw-bulk-btn').disabled = true;
        document.getElementById('gkw-fill').style.width = '0%';
        document.getElementById('gkw-fill').textContent = '0%';
        processGKWBatch();
    }

    function resumeGKWBulk() {
        const saved = localStorage.getItem('sgclabs_gkw_progress');
        if (saved) { try { gkwOffset = JSON.parse(saved).offset; } catch(e) {} }
        gkwErrors = 0; gkwRunning = true;
        document.getElementById('gkw-bulk-btn').disabled = true;
        processGKWBatch();
    }

    function processGKWBatch() {
        if (!gkwRunning) return;
        jQuery.ajax({
            url: ajaxurl, type: 'POST', timeout: 120000,
            data: { action: 'sgclabs_bulk_global_kw', nonce: nonce, offset: gkwOffset },
            success: function(res) {
                gkwErrors = 0;
                if (!res.success) { handleGKWError('Server error'); return; }
                const d = res.data;
                localStorage.setItem('sgclabs_gkw_progress', JSON.stringify({offset:d.processed,total:d.total}));
                document.getElementById('gkw-fill').style.width = d.percent+'%';
                document.getElementById('gkw-fill').textContent = d.percent+'%';
                document.getElementById('gkw-status').innerHTML =
                    '⚙️ <strong>'+d.processed+'/'+d.total+'</strong> products done ('+d.percent+'%) | This batch: +'+d.kw_this_batch+' keywords &nbsp;'
                    +'<button class="button" onclick="gkwRunning=false;document.getElementById(\'gkw-bulk-btn\').disabled=false;">⏸️ Pause</button>';
                if (!d.done) { gkwOffset = d.next_offset; setTimeout(processGKWBatch, 200); }
                else {
                    gkwRunning = false;
                    localStorage.removeItem('sgclabs_gkw_progress');
                    document.getElementById('gkw-fill').style.width = '100%';
                    document.getElementById('gkw-fill').textContent = '100%';
                    document.getElementById('gkw-status').innerHTML = '🎉 <strong>Complete!</strong> Global keywords generated for all '+d.total+' products!';
                    document.getElementById('gkw-bulk-btn').disabled = false;
                    setTimeout(()=>location.reload(), 3000);
                }
            },
            error: function(xhr,status) { handleGKWError('Network: '+status); }
        });
    }

    function handleGKWError(msg) {
        gkwErrors++;
        if (gkwErrors <= 5) {
            const wait = gkwErrors * 4;
            document.getElementById('gkw-status').innerHTML = '⚠️ Retry '+gkwErrors+'/5: '+msg+' — '+wait+'s mein retry...';
            setTimeout(processGKWBatch, wait*1000);
        } else {
            gkwRunning = false;
            document.getElementById('gkw-status').innerHTML = '❌ Failed. <button class="button button-primary" onclick="resumeGKWBulk()">▶️ Resume</button>';
            document.getElementById('gkw-bulk-btn').disabled = false;
        }
    }

    function clearGKWCache() {
        if (!confirm('Clear all global keywords cache?')) return;
        jQuery.post(ajaxurl,{action:'sgclabs_clear_gkw_cache',nonce},function(res){
            if(res.success){alert('✅ Cache cleared!');location.reload();}
        });
    }

    function previewGKW() {
        const pid = document.getElementById('gkw-product').value;
        const country = document.getElementById('gkw-country-filter').value;
        if (!pid) { alert('Product select karo'); return; }
        jQuery.post(ajaxurl,{action:'sgclabs_preview_global_kw',nonce,product_id:pid,country},function(res){
            if (!res.success) return;
            document.getElementById('gkw-preview').style.display='block';
            document.getElementById('gkw-preview-count').textContent = 'Total: '+res.data.total+' keywords | Showing: '+res.data.keywords.length+(country?' for '+country:'');
            document.getElementById('gkw-preview-tags').innerHTML = res.data.keywords.map(k=>`<span class="sgclabs-tag" style="font-size:11px;">${k}</span>`).join('');
        });
    }
    }); // end DOMContentLoaded
    </script>
    <style>
    .button-hero{font-size:15px!important;padding:10px 25px!important;height:auto!important;}
    </style>
    <?php
}

// Clear cache AJAX
add_action('wp_ajax_sgclabs_clear_gkw_cache', 'sgclabs_clear_gkw_cache_ajax');
function sgclabs_clear_gkw_cache_ajax() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_sgclabs_global_kw','_sgclabs_global_kw_count','_sgclabs_global_kw_time')");
    wp_send_json_success();
}
