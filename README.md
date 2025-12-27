# MetaKit

Laravel için URL bazlı SEO meta tag yönetim paketi. Her sayfa için özel meta bilgileri tanımlayın, cache desteği ile hızlı çalışın.

## Özellikler

- 🎯 URL bazlı meta yönetimi (domain, path, query parametreleri)
- 🚀 Otomatik cache sistemi
- 🎨 Blade direktifleri ile kolay kullanım
- 📱 Open Graph ve Twitter Card desteği
- 📊 JSON-LD schema desteği
- 🔄 RESTful API ile yönetim
- 🛡️ Duplicate meta tag koruması
- 🗺️ Otomatik sitemap oluşturma
- 🎨 Bootstrap uyumlu admin arayüzü

## Kurulum

### 1. Composer ile Paketi Ekleyin

`composer.json` dosyanıza path repository ekleyin:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "modules/packages/tunasahincomtr/metakit",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "tunasahincomtr/metakit": "*"
    },
    "autoload": {
        "psr-4": {
            "TunaSahincomtr\\MetaKit\\": "modules/packages/tunasahincomtr/metakit/src/"
        },
        "files": [
            "modules/packages/tunasahincomtr/metakit/src/Support/helpers.php"
        ]
    }
}
```

Ardından paketi yükleyin:

```bash
composer require tunasahincomtr/metakit:"*"
composer dump-autoload
```

### 2. Config ve Migration

Config dosyasını yayınlayın:

```bash
php artisan vendor:publish --tag=metakit-config
```

Migration'ları çalıştırın:

```bash
php artisan migrate
```

Hepsi bu kadar! Paket Laravel'in auto-discovery özelliği sayesinde otomatik olarak yüklenir.

## Hızlı Başlangıç

### Blade Template'inde Kullanım

```blade
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    @metakit
    @metakitJsonLd
</head>
<body>
    <!-- İçerik -->
</body>
</html>
```

`@metakit` direktifi şunları otomatik oluşturur:
- `<title>` tagı
- Meta description, keywords, robots
- Canonical URL
- Open Graph tagları
- Twitter Card tagları

### Controller'da Programatik Kullanım

```php
use function TunaSahincomtr\MetaKit\metakit;

public function show(Product $product)
{
    metakit()
        ->setTitle($product->name . ' - Ürün Detayı')
        ->setDescription($product->description)
        ->setOgImage($product->image_url);

    return view('products.show', compact('product'));
}
```

## Admin Arayüzü

MetaKit, Bootstrap 5 uyumlu bir admin arayüzü ile gelir. Sadece bir Blade direktifi ile kullanabilirsiniz:

```blade
@metakitform
```

Bu direktif ile şunları yapabilirsiniz:
- ✅ Sayfa ekleme, düzenleme, silme
- ✅ Liste görünümü ile pagination
- ✅ Arama ve filtreleme
- ✅ SEO skoru görüntüleme
- ✅ İstatistikler ve raporlar
- ✅ JSON-LD schema yönetimi

### Renk Özelleştirme

Admin arayüzünün renklerini CSS variable ile özelleştirebilirsiniz:

```css
.metakit-form-wrapper {
    --metakit-primary: #198754; /* Buton arka plan rengi */
    --metakit-primary-text: #ffffff; /* Buton metin rengi */
}
```

## API Kullanımı

MetaKit RESTful API ile çalışır. API endpoint'leri:

**Public Endpoints (Token gerekmez):**
- `GET /api/metakit/pages` - Sayfa listesi
- `GET /api/metakit/pages/{id}` - Tek sayfa
- `GET /api/metakit/stats/dashboard` - İstatistikler

**Protected Endpoints (Token gerekli):**
- `POST /api/metakit/pages` - Yeni sayfa
- `PUT /api/metakit/pages/{id}` - Sayfa güncelle
- `DELETE /api/metakit/pages/{id}` - Sayfa sil
- `POST /api/metakit/pages/import/csv` - CSV import
- `GET /api/metakit/pages/export/csv` - CSV export

API token oluşturmak için:

```php
$user = User::first();
$token = $user->createToken('api-token')->plainTextToken;
```

## Blade Direktifleri

### Tüm Meta Tagları
```blade
@metakit
```

### Sadece Title
```blade
<title>@metakitTitle</title>
```

### Belirli Meta Değeri
```blade
<meta name="description" content="@metakitMeta('description')">
```

### JSON-LD Schema
```blade
@metakitJsonLd
```

## JSON-LD Schema Yönetimi

MetaKit ile sayfanıza birden fazla JSON-LD schema ekleyebilirsiniz:

```php
metakit()->addJsonLd([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => 'Makale Başlığı',
    'author' => [
        '@type' => 'Person',
        'name' => 'Yazar Adı'
    ]
]);

// Veya Product schema
metakit()->addJsonLd([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => 'Ürün Adı',
    'price' => '99.99',
    'currency' => 'TRY'
]);
```

Admin arayüzünde hazır template'ler ile kolayca schema ekleyebilirsiniz:
- Article, BlogPosting
- Product
- FAQPage
- BreadcrumbList
- Organization, WebSite
- LocalBusiness, Person
- Ve daha fazlası...

## Sitemap

MetaKit otomatik olarak sitemap.xml oluşturur. Aktif sayfalarınız otomatik olarak sitemap'e eklenir:

```
GET /sitemap.xml
```

Config'de özelleştirebilirsiniz:

```php
// config/metakit.php
'sitemap' => [
    'enabled' => true,
    'route' => '/sitemap.xml',
    'include_images' => true,
    'only_active' => true,
],
```

## Cache Yönetimi

MetaKit otomatik cache kullanır. Sayfa oluşturulduğunda, güncellendiğinde veya silindiğinde ilgili cache otomatik temizlenir.

Manuel cache temizleme:

```php
metakit()->purgeCache('example.com', '/products', $queryHash);
```

## Query Hash

URL'deki query parametreleri için farklı meta tanımlamak istediğinizde query hash kullanılır. Sadece `config/metakit.php` içindeki `query_whitelist` listesindeki parametreler hash hesaplamasına dahil edilir.

**Örnek:**
- URL: `https://example.com/products?city=istanbul&type=apartment&page=2`
- Whitelist: `['city', 'type']`
- Query Hash: `city=istanbul&type=apartment` parametrelerinden oluşturulur
- `page` parametresi hash'e dahil edilmez

## Konfigürasyon

`config/metakit.php` dosyasında aşağıdaki ayarları yapabilirsiniz:

```php
'api_prefix' => 'api/metakit',
'cache_ttl_minutes' => 360,
'query_whitelist' => ['city', 'type', 'price_min'],
'default' => [
    'site_name' => env('APP_NAME', 'Laravel'),
    'title_suffix' => ' - ' . env('APP_NAME', 'Laravel'),
    'default_image' => '/images/og-default.jpg',
],
'sitemap' => [
    'enabled' => true,
    'route' => '/sitemap.xml',
],
'form' => [
    'auth_required' => false, // Admin arayüzü için auth kontrolü
],
```

## Örnekler

### Basit Kullanım
```blade
<head>
    @metakit
</head>
```

### Dinamik Override
```php
// Controller'da
metakit()
    ->setTitle('Özel Başlık')
    ->setDescription('Özel Açıklama')
    ->setCanonical('https://example.com/canonical')
    ->setOgImage('https://example.com/image.jpg');
```

### API ile Toplu İşlemler
```javascript
// JavaScript
fetch('/api/metakit/pages', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        domain: window.location.hostname,
        path: window.location.pathname,
        title: 'Sayfa Başlığı',
        description: 'Sayfa Açıklaması',
        status: 'active',
    }),
});
```

## Gereksinimler

- PHP >= 8.1
- Laravel >= 10.0
- Laravel Sanctum (API için)

## Lisans

MIT

## Destek

Sorularınız için: info@tunasahin.com.tr
