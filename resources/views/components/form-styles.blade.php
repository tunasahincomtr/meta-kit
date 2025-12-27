{{-- 
    MetaKit Form Component Styles
    
    Primary color'ı değiştirmek için CSS variable'larını kullanın:
    --metakit-primary: #198754; (Buton arka plan rengi)
    --metakit-primary-text: #ffffff; (Buton metin rengi - varsayılan beyaz)
    
    Kendi CSS dosyanızda veya <style> tag'inde override edebilirsiniz:
    .metakit-form-wrapper { 
        --metakit-primary: #your-color; 
        --metakit-primary-text: #ffffff; /* Açık renkler için beyaz, koyu renkler için siyah (#000000) */
    }
    
    NOT: Artık ENV/Config'den okunmuyor, sadece CSS variable kullanılıyor!
--}}

<style>
    /* 
        Primary color CSS variable - Varsayılan değer
        Bu değişkeni kendi CSS'inizde override ederek primary color'ı değiştirebilirsiniz
        NOT: Bu varsayılan değerler, kullanıcı CSS'inde override edilmediyse kullanılır
    */
    .metakit-form-wrapper {
        --metakit-primary: #0d6efd; /* Bootstrap default primary blue - Override edilebilir */
        --metakit-primary-text: #ffffff; /* Primary buton metin rengi - varsayılan beyaz */
    }

    /* Primary color için tüm stiller - CSS variable kullanıyor */
    .metakit-form-wrapper .btn-primary {
        background-color: var(--metakit-primary) !important;
        border-color: var(--metakit-primary) !important;
        color: var(--metakit-primary-text, #ffffff) !important; /* Metin rengi - varsayılan beyaz */
    }

    .metakit-form-wrapper .btn-primary:hover,
    .metakit-form-wrapper .btn-primary:focus,
    .metakit-form-wrapper .btn-primary:active {
        background-color: var(--metakit-primary) !important;
        border-color: var(--metakit-primary) !important;
        color: var(--metakit-primary-text, #ffffff) !important;
        opacity: 0.9;
    }

    .metakit-form-wrapper .btn-outline-primary {
        color: var(--metakit-primary) !important;
        border-color: var(--metakit-primary) !important;
        background-color: transparent !important;
    }

    .metakit-form-wrapper .btn-outline-primary:hover,
    .metakit-form-wrapper .btn-outline-primary:focus,
    .metakit-form-wrapper .btn-outline-primary:active {
        background-color: var(--metakit-primary) !important;
        border-color: var(--metakit-primary) !important;
        color: var(--metakit-primary-text, #ffffff) !important; /* Hover durumunda metin rengi */
    }

    .metakit-form-wrapper .text-primary {
        color: var(--metakit-primary) !important;
    }

    .metakit-form-wrapper .bg-primary {
        background-color: var(--metakit-primary) !important;
    }

    .metakit-form-wrapper .border-primary {
        border-color: var(--metakit-primary) !important;
    }

    /* Nav tabs active state */
    .metakit-form-wrapper .nav-tabs .nav-link.active {
        color: var(--metakit-primary) !important;
        border-bottom-color: var(--metakit-primary) !important;
        background-color: transparent !important;
    }

    /* Nav pills active state - Form içindeki tab butonları */
    .metakit-form-wrapper .nav-pills .nav-link.active {
        background-color: var(--metakit-primary) !important;
        color: var(--metakit-primary-text, #ffffff) !important;
    }

    /* Nav pills active button element */
    .metakit-form-wrapper .nav-pills button.nav-link.active {
        background-color: var(--metakit-primary) !important;
        color: var(--metakit-primary-text, #ffffff) !important;
    }

    /* Nav link text color - ensure text is visible */
    .metakit-form-wrapper .nav-link {
        color: #6c757d !important;
    }

    .metakit-form-wrapper .nav-link:not(.active) {
        color: #6c757d !important;
    }

    /* Button nav-link text color */
    .metakit-form-wrapper button.nav-link {
        color: #6c757d !important;
    }

    .metakit-form-wrapper button.nav-link.active {
        color: var(--metakit-primary-text, #ffffff) !important;
        background-color: var(--metakit-primary) !important;
    }

    .metakit-form-wrapper .spinner-border.text-primary {
        color: var(--metakit-primary) !important;
    }

    /* Genel stil düzenlemeleri */
    .metakit-form-wrapper {
        padding: 1rem 0;
    }

    .metakit-form-wrapper .card {
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .metakit-form-wrapper .nav-tabs {
        border-bottom: 2px solid #dee2e6;
    }

    .metakit-form-wrapper .nav-link:hover:not(.active) {
        color: var(--metakit-primary, #0d6efd) !important;
        border-color: transparent;
    }
</style>
