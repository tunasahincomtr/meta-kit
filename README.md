# MetaKit - Laravel SEO Meta Management Package

MetaKit, Laravel projeleri için URL bazlı SEO meta tag yönetim paketidir. Her sayfa için özel meta bilgileri tanımlayabilir, cache desteği ile hızlı çalışır ve RESTful API ile kolayca yönetebilirsiniz.

## Özellikler

-   ✅ URL bazlı meta tag yönetimi (domain, path, query hash)
-   ✅ Blade direktifleri ile kolay kullanım
-   ✅ RESTful API ile CRUD işlemleri
-   ✅ Otomatik cache yönetimi
-   ✅ Fallback değerler desteği
-   ✅ Programatik override desteği
-   ✅ JSON-LD schema desteği
-   ✅ Open Graph ve Twitter Card desteği

## Kurulum

### 1. Paket Klasör Yapısı

Paket dosyaları projenizin içinde `modules/packages/tunasahincomtr/metakit` klasöründe bulunmalıdır.

### 2. Composer Entegrasyonu

Ana projenin `composer.json` dosyasına path repository ekleyin:

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

### 3. Paketi Yükleyin

```bash
composer require tunasahincomtr/metakit:"*"
composer dump-autoload
```

### 4. Service Provider Kaydı

`app/Providers/AppServiceProvider.php` dosyasına ekleyin:

```php
use TunaSahincomtr\MetaKit\MetaKitServiceProvider;

public function register(): void
{
    $this->app->register(MetaKitServiceProvider::class);
}
```

### 5. Config Dosyasını Yayınlayın

```bash
php artisan vendor:publish --tag=metakit-config
```

### 6. Migration'ları Çalıştırın

```bash
php artisan migrate
```

## Test Sayfası Oluşturma

Hızlı bir test için `routes/web.php` dosyasına ekleyin:

```php
Route::get('/test-metakit', function () {
    // Override örneği
    metakit()
        ->setTitle('Test Sayfası - MetaKit')
        ->setDescription('Bu bir MetaKit test sayfasıdır.');

    return view('test-metakit');
});
```

`resources/views/test-metakit.blade.php` dosyası oluşturun:

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
    <h1>MetaKit Test Sayfası</h1>

    <h2>Meta Bilgileri:</h2>
    <ul>
        <li><strong>Title:</strong> @metakitTitle</li>
        <li><strong>Description:</strong> @metakitMeta('description')</li>
        <li><strong>Canonical:</strong> {{ metakit()->getMeta('canonical_url') }}</li>
    </ul>
</body>
</html>
```

Tarayıcıda `http://localhost:8000/test-metakit` adresini açarak test edebilirsiniz.

## Kullanım

### Blade Direktifleri

#### Tüm Meta Tagları

```blade
<head>
    @metakit
</head>
```

Bu direktif şunları oluşturur:

-   `<title>` tagı
-   Meta description, keywords, robots
-   Canonical link
-   Open Graph tagları
-   Twitter Card tagları

#### Sadece Title

```blade
<title>@metakitTitle</title>
```

#### Belirli Meta Değeri

```blade
<meta name="description" content="@metakitMeta('description')">
<meta name="keywords" content="@metakitMeta('keywords')">
```

#### JSON-LD Scriptleri

```blade
@metakitJsonLd
```

#### Debug Yorumları (Sadece local ortamda)

```blade
@metakitDebug
```

### Programatik Kullanım

#### Helper Fonksiyonu

```php
use function TunaSahincomtr\MetaKit\metakit;

// Meta bilgilerini al
$title = metakit()->getTitle();
$description = metakit()->getMeta('description');
$allMeta = metakit()->current();
```

#### Override Metodları

```php
metakit()
    ->setTitle('Özel Başlık')
    ->setDescription('Özel Açıklama')
    ->setCanonical('https://example.com/canonical')
    ->setRobots('noindex, nofollow')
    ->setOgImage('https://example.com/image.jpg')
    ->addJsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => 'Makale Başlığı',
    ]);
```

#### Toplu Override

```php
metakit()->set([
    'title' => 'Başlık',
    'description' => 'Açıklama',
    'og_image' => 'https://example.com/image.jpg',
]);
```

### API Kullanımı

Tüm API endpoint'leri varsayılan olarak `auth:sanctum` middleware'i ile korunmaktadır.

#### Base URL

```
/api/metakit
```

#### Endpoint'ler

**Sayfa Listesi**

```bash
GET /api/metakit/pages?domain=example.com&status=active&per_page=20
```

**Yeni Sayfa Oluştur**

```bash
POST /api/metakit/pages
Content-Type: application/json

{
    "domain": "example.com",
    "path": "/products",
    "title": "Ürünler",
    "description": "Ürünler sayfası açıklaması",
    "status": "active"
}
```

**Sayfa Güncelle**

```bash
PUT /api/metakit/pages/{id}
Content-Type: application/json

{
    "title": "Güncellenmiş Başlık",
    "status": "active"
}
```

**Sayfa Sil**

```bash
DELETE /api/metakit/pages/{id}
```

**Hızlı Oluştur (URL'den)**

```bash
POST /api/metakit/pages/quick-create
Content-Type: application/json

{
    "url": "https://example.com/products?city=istanbul",
    "status": "draft"
}
```

#### Sanctum Token ile Kullanım

```bash
# Token oluştur
php artisan tinker
$user = \App\Models\User::first();
$token = $user->createToken('api-token')->plainTextToken;

# API çağrısı
curl -X GET "http://localhost:8000/api/metakit/pages" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## Konfigürasyon

`config/metakit.php` dosyasında aşağıdaki ayarları yapabilirsiniz:

-   `api_prefix`: API route prefix (varsayılan: `api/metakit`)
-   `api_middleware`: API middleware'leri (varsayılan: `['api', 'auth:sanctum']`)
-   `query_whitelist`: Query hash hesaplamasına dahil edilecek parametreler
-   `cache_ttl_minutes`: Cache süresi (dakika)
-   `default`: Varsayılan değerler (site_name, title_suffix, default_image, default_robots)
-   `debug_comments`: Debug HTML yorumları (varsayılan: `false`)

## Kullanım Senaryoları

### Senaryo 1: Basit Kullanım

```blade
<head>
    @metakit
</head>
```

### Senaryo 2: Override ile Dinamik İçerik

```php
// Controller'da
public function show(Product $product)
{
    metakit()
        ->setTitle($product->name . ' - Ürün Detayı')
        ->setDescription($product->description)
        ->setOgImage($product->image_url);

    return view('products.show', compact('product'));
}
```

### Senaryo 3: JSON-LD ile Schema Markup

```php
metakit()->addJsonLd([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => 'Ürün Adı',
    'description' => 'Ürün Açıklaması',
    'image' => 'https://example.com/image.jpg',
]);
```

### Senaryo 4: API ile Toplu İçerik Yönetimi

Admin panelinizden API kullanarak tüm sayfaların meta bilgilerini yönetebilirsiniz. Örneğin:

```javascript
// JavaScript ile API çağrısı
fetch("/api/metakit/pages", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        domain: window.location.hostname,
        path: window.location.pathname,
        title: "Sayfa Başlığı",
        description: "Sayfa Açıklaması",
        status: "active",
    }),
});
```

## Cache Yönetimi

MetaKit otomatik olarak cache kullanır. Bir sayfa oluşturulduğunda, güncellendiğinde veya silindiğinde ilgili cache otomatik olarak temizlenir.

Manuel cache temizleme:

```php
metakit()->purgeCache('example.com', '/products', $queryHash);
```

## Query Hash

Query hash, URL'deki query parametrelerinden oluşturulur. Sadece `config/metakit.php` içindeki `query_whitelist` listesindeki parametreler hash hesaplamasına dahil edilir.

**Örnek:**

-   URL: `https://example.com/products?city=istanbul&type=apartment&page=2`
-   Whitelist: `['city', 'type']`
-   Query Hash: `city=istanbul&type=apartment` parametrelerinden oluşturulur
-   `page` parametresi hash'e dahil edilmez

## Lisans

MIT

## Destek

Sorularınız için: info@tunasahin.com.tr
