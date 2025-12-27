{{-- 
    MetaKit Form Component
    
    Bu component Bootstrap 5 ile uyumlu, tam özellikli bir SEO meta yönetim arayüzü sağlar.
    
    Özellikler:
    - CRUD işlemleri (Create, Read, Update, Delete)
    - Liste görünümü ile pagination
    - Arama ve filtreleme
    - Detaylı JSON-LD schema template'leri
    - Tab-based form yapısı
    - Bootstrap 5 uyumlu tasarım
    - Config'den primary color desteği
    
    Kullanım:
    @metakitform
    
    Bu directive auth kontrolü yapar ve sadece giriş yapmış kullanıcılar için formu gösterir.
--}}

{{-- Include MetaKit Form Styles (Primary Color CSS Variable) --}}
@include('metakit::components.form-styles')

<div id="metakit-form-container" class="metakit-form-wrapper">
    {{-- Alert Container for Messages --}}
    <div id="metakit-alert-container" class="mb-4"></div>

    {{-- Navigation Tabs --}}
    <ul class="nav nav-tabs mb-4" id="metakitTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button"
                role="tab" aria-controls="list" aria-selected="true">
                <i class="bi bi-list-ul"></i> Liste
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="form-tab" data-bs-toggle="tab" data-bs-target="#form" type="button"
                role="tab" aria-controls="form" aria-selected="false">
                <i class="bi bi-plus-circle"></i> Yeni Sayfa Ekle
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button"
                role="tab" aria-controls="stats" aria-selected="false">
                <i class="bi bi-bar-chart"></i> İstatistikler
            </button>
        </li>
    </ul>

    {{-- Tab Content --}}
    <div class="tab-content" id="metakitTabContent">
        {{-- LIST TAB --}}
        <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Meta Sayfaları</h5>
                    <div class="d-flex gap-2">
                        {{-- Search Input --}}
                        <input type="text" id="searchPages" class="form-control form-control-sm" placeholder="Ara..."
                            style="width: 200px;" onkeyup="debounceSearch()">
                        {{-- Refresh Button --}}
                        <button class="btn btn-sm btn-outline-primary" onclick="loadPages(1)"
                            id="btnRefreshPages">
                            <i class="bi bi-arrow-clockwise"></i> Yenile
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="pagesList">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Yükleniyor...</span>
                            </div>
                        </div>
                    </div>
                    {{-- Pagination --}}
                    <div id="pagination" class="mt-3"></div>
                </div>
            </div>
        </div>

        {{-- FORM TAB --}}
        <div class="tab-pane fade" id="form" role="tabpanel" aria-labelledby="form-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Sayfa Ekle / Düzenle</h5>
                </div>
                <div class="card-body">
                    {{-- Form will be inserted here by JavaScript --}}
                    <div id="pageFormContainer">
                        <div class="text-center py-4 text-muted">
                            Form yükleniyor...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- STATS TAB --}}
        <div class="tab-pane fade" id="stats" role="tabpanel" aria-labelledby="stats-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> İstatistikler</h5>
                </div>
                <div class="card-body">
                    <div id="statsContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Yükleniyor...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 
    JavaScript Code
    
    Bu bölüm tüm CRUD işlemleri, API çağrıları, form yönetimi ve JSON-LD template'leri içerir.
    
    Not: Bu dosya çok büyük olduğu için, JavaScript kodu ayrı bir dosyaya taşınabilir.
    Şimdilik inline olarak bırakıyoruz.
--}}
<script>
    (function() {
        'use strict';

        // ============================================
        // CONFIGURATION
        // ============================================

        /**
         * API Prefix - MetaKit API endpoint prefix
         * Config'den gelir: metakit.api_prefix
         */
        const API_PREFIX = '{{ $apiPrefix }}';

        /**
         * Primary Color - Artık CSS variable'dan okunuyor (--metakit-primary)
         * Config/ENV kullanılmıyor, sadece CSS'den çekiliyor
         */
        const PRIMARY_COLOR_CLASS = 'primary'; // Bootstrap class (CSS variable kullanılıyor)

        /**
         * Helper function to get button class with primary color
         */
        function getPrimaryButtonClass() {
            return PRIMARY_COLOR_CLASS;
        }

        /**
         * Helper function to get primary color class (alias for compatibility)
         */
        function getPrimaryColorClass() {
            return PRIMARY_COLOR_CLASS;
        }

        /**
         * Current Page for Pagination
         */
        let currentPage = 1;

        /**
         * API Token (stored in localStorage)
         */
        let currentToken = localStorage.getItem('metakit_token') || '';

        // ============================================
        // UTILITY FUNCTIONS
        // ============================================

        /**
         * Show alert message
         * @param {string} message - Message to display
         * @param {string} type - Alert type: 'success', 'error', 'warning', 'info'
         */
        function showMessage(message, type = 'info') {
            const alertContainer = document.getElementById('metakit-alert-container');
            const alertTypes = {
                'success': 'success',
                'error': 'danger',
                'warning': 'warning',
                'info': 'info'
            };

            const alertClass = alertTypes[type] || 'info';
            const icon = {
                'success': 'check-circle',
                'danger': 'exclamation-triangle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            } [alertClass] || 'info-circle';

            const alertHtml = `
            <div class="alert alert-${alertClass} alert-dismissible fade show" role="alert">
                <i class="bi bi-${icon}"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

            alertContainer.innerHTML = alertHtml;

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }

        /**
         * Make API call
         * @param {string} endpoint - API endpoint (without prefix)
         * @param {string} method - HTTP method: 'GET', 'POST', 'PUT', 'DELETE'
         * @param {object} data - Request body data (optional)
         * @returns {Promise} - Response data
         */
        async function apiCall(endpoint, method = 'GET', data = null) {
            const url = `/${API_PREFIX}${endpoint}`;
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                }
            };

            // Add token if available (for POST/PUT/DELETE)
            if (currentToken && ['POST', 'PUT', 'DELETE'].includes(method)) {
                options.headers['Authorization'] = `Bearer ${currentToken}`;
            }

            // Add body for POST/PUT
            if (data && ['POST', 'PUT'].includes(method)) {
                options.body = JSON.stringify(data);
            }

            try {
                const response = await fetch(url, options);
                const responseData = await response.json();

                if (!response.ok) {
                    throw new Error(responseData.message || `HTTP ${response.status}`);
                }

                return responseData;
            } catch (error) {
                console.error('API Call Error:', error);
                throw error;
            }
        }

        /**
         * Debounce function for search
         */
        let searchTimeout;

        function debounceSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadPages(1);
            }, 500);
        }

        // ============================================
        // INITIALIZATION
        // ============================================

        /**
         * Initialize MetaKit Form
         * Bu fonksiyon sayfa yüklendiğinde çağrılır ve ilk verileri yükler.
         */
        function initMetaKitForm() {
            // Load initial data
            loadPages(1);
            loadStats();
            loadForm();

            // Check for token
            if (!currentToken) {
                showMessage(
                    '⚠️ API token bulunamadı. POST/PUT/DELETE işlemleri için token gerekli. Token almak için API endpoint\'ini kullanın.',
                    'warning');
            }
        }

        // Run initialization when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initMetaKitForm);
        } else {
            initMetaKitForm();
        }

        // ============================================
        // PAGES - LIST & PAGINATION
        // ============================================

        /**
         * Load pages with pagination
         * @param {number} page - Page number (default: 1)
         */
        async function loadPages(page = 1) {
            try {
                currentPage = page;
                const searchQuery = document.getElementById('searchPages')?.value || '';
                const primaryColorClass = 'primary'; // CSS variable kullanılıyor
                let endpoint = `/pages?per_page=20&page=${page}&include_seo_score=true`;

                if (searchQuery.trim()) {
                    endpoint += `&q=${encodeURIComponent(searchQuery.trim())}`;
                }

                document.getElementById('pagesList').innerHTML =
                    '<div class="text-center py-4"><div class="spinner-border text-' + primaryColorClass +
                    '" role="status"></div></div>';

                const response = await apiCall(endpoint);

                // Handle Laravel Resource Collection format
                const pages = response.data || [];
                const meta = response.meta || {};
                const links = response.links || {};

                if (pages && Array.isArray(pages) && pages.length > 0) {
                    const primaryColorClass = 'primary'; // CSS variable kullanılıyor
                    let html =
                        '<div class="table-responsive"><table class="table table-striped table-hover"><thead><tr>';
                    html +=
                        '<th>ID</th><th>Domain</th><th>Path</th><th>Title</th><th>Status</th><th>SEO Score</th><th>İşlemler</th>';
                    html += '</tr></thead><tbody>';

                    pages.forEach(page => {
                        const statusBadge = page.status === 'active' ? 'success' : 'secondary';
                        const seoScore = page.seo_score?.score || 0;
                        const scoreClass = seoScore >= 80 ? 'success' : seoScore >= 60 ? 'warning' :
                            'danger';

                        // Build recommendations tooltip
                        const recommendations = page.seo_score?.recommendations || [];
                        const breakdown = page.seo_score?.breakdown || {};
                        let tooltipContent = '<strong>SEO Skoru Detayları:</strong><br>';

                        // Add breakdown info
                        if (breakdown && Object.keys(breakdown).length > 0) {
                            tooltipContent += '<small>';
                            Object.entries(breakdown).forEach(([key, value]) => {
                                const label = {
                                    'has_title': 'Başlık',
                                    'has_description': 'Açıklama',
                                    'has_og_image': 'OG Görsel',
                                    'has_canonical': 'Canonical',
                                    'has_jsonld': 'JSON-LD',
                                    'title_length_ok': 'Başlık Uzunluğu',
                                    'description_length_ok': 'Açıklama Uzunluğu',
                                    'has_keywords': 'Keywords',
                                    'has_robots': 'Robots'
                                } [key] || key;

                                const icon = value ? '✅' : '❌';
                                tooltipContent += `${icon} ${label}<br>`;
                            });
                            tooltipContent += '</small>';
                        }

                        // Add recommendations
                        if (recommendations.length > 0) {
                            tooltipContent += '<br><strong>Öneriler:</strong><br><small>';
                            recommendations.forEach(rec => {
                                tooltipContent += `• ${rec}<br>`;
                            });
                            tooltipContent += '</small>';
                        }

                        // Escape HTML for tooltip attribute
                        const tooltipHtml = tooltipContent.replace(/'/g, "&apos;").replace(/"/g,
                            "&quot;");

                        html += `<tr>
                        <td>${page.id}</td>
                        <td><code>${page.domain}</code></td>
                        <td><code>${page.path}</code></td>
                        <td>${page.title || '-'}</td>
                        <td><span class="badge bg-${statusBadge}">${page.status}</span></td>
                        <td>
                            <span class="badge bg-${scoreClass}" 
                                  data-bs-toggle="tooltip" 
                                  data-bs-html="true" 
                                  data-bs-placement="left"
                                  title="${tooltipHtml}">
                                ${seoScore}/100
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-${primaryColorClass}" onclick="editPage(${page.id})" title="Düzenle">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deletePage(${page.id})" title="Sil">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                    });

                    html += '</tbody></table></div>';
                    document.getElementById('pagesList').innerHTML = html;

                    // Render pagination
                    renderPagination(meta, links);
                } else {
                    document.getElementById('pagesList').innerHTML =
                        '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Henüz sayfa yok. Yeni sayfa eklemek için "Yeni Sayfa Ekle" tab\'ına geçin.</div>';
                    document.getElementById('pagination').innerHTML = '';
                }
            } catch (error) {
                console.error('Load Pages Error:', error);
                showMessage('Sayfalar yüklenemedi: ' + (error.message || 'Bilinmeyen hata'), 'error');
                document.getElementById('pagesList').innerHTML =
                    '<div class="alert alert-danger">Yükleme hatası: ' + (error.message || 'Bilinmeyen hata') +
                    '</div>';
            }
        }

        /**
         * Render pagination controls
         * @param {object} meta - Pagination meta data
         * @param {object} links - Pagination links
         */
        function renderPagination(meta, links) {
            const paginationDiv = document.getElementById('pagination');

            if (!meta || (meta.last_page !== undefined && meta.last_page <= 1)) {
                paginationDiv.innerHTML = '';
                return;
            }

            const current = meta.current_page || 1;
            const lastPage = meta.last_page || 1;
            const total = meta.total || 0;

            let html = `<div class="d-flex justify-content-between align-items-center">
            <div><small class="text-muted">Toplam ${total} kayıt, Sayfa ${current}/${lastPage}</small></div>
            <nav>
                <ul class="pagination pagination-sm mb-0">`;

            // Previous button
            if (links.prev) {
                html +=
                    `<li class="page-item"><a class="page-link" href="#" onclick="loadPages(${current - 1}); return false;">Önceki</a></li>`;
            } else {
                html += `<li class="page-item disabled"><span class="page-link">Önceki</span></li>`;
            }

            // Page numbers
            const startPage = Math.max(1, current - 2);
            const endPage = Math.min(lastPage, current + 2);

            for (let i = startPage; i <= endPage; i++) {
                if (i === current) {
                    html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
                } else {
                    html +=
                        `<li class="page-item"><a class="page-link" href="#" onclick="loadPages(${i}); return false;">${i}</a></li>`;
                }
            }

            // Next button
            if (links.next) {
                html +=
                    `<li class="page-item"><a class="page-link" href="#" onclick="loadPages(${current + 1}); return false;">Sonraki</a></li>`;
            } else {
                html += `<li class="page-item disabled"><span class="page-link">Sonraki</span></li>`;
            }

            html += `</ul></nav></div>`;
            paginationDiv.innerHTML = html;
        }

        // ============================================
        // STATS
        // ============================================

        /**
         * Load statistics - Dashboard view
         */
        async function loadStats() {
            try {
                const stats = await apiCall('/stats/dashboard');
                const primaryColorClass = 'primary'; // CSS variable kullanılıyor

                // API response structure: summary is nested
                const summary = {
                    total_pages: stats.summary?.total_pages || 0,
                    active_pages: stats.summary?.active_pages || 0,
                    draft_pages: stats.summary?.draft_pages || 0,
                    active_percentage: stats.summary?.active_percentage || 0,
                    draft_percentage: stats.summary?.draft_percentage || 0,
                    recent_updates: stats.summary?.recent_updates || 0
                };
                const missingMeta = stats.missing_meta || {};
                const duplicates = stats.duplicates || {};
                const domainBreakdown = stats.domain_breakdown || {};

                let html = '';

                // ========== SUMMARY CARDS ==========
                html += '<div class="row g-4 mb-4">';

                // Toplam Sayfa
                html += `
                    <div class="col-md-3 col-sm-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.75rem;">Toplam Sayfa</h6>
                                        <h2 class="mb-0">${summary.total_pages || 0}</h2>
                                    </div>
                                    <div class="text-${primaryColorClass}" style="font-size: 2.5rem; opacity: 0.2;">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Aktif Sayfa
                const activePercentage = summary.active_percentage || 0;
                html += `
                    <div class="col-md-3 col-sm-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.75rem;">Aktif Sayfalar</h6>
                                        <h2 class="mb-0 text-success">${summary.active_pages || 0}</h2>
                                        <small class="text-muted">%${activePercentage.toFixed(1)}</small>
                                    </div>
                                    <div class="text-success" style="font-size: 2.5rem; opacity: 0.2;">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Taslak Sayfa
                const draftPercentage = summary.draft_percentage || 0;
                html += `
                    <div class="col-md-3 col-sm-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.75rem;">Taslak Sayfalar</h6>
                                        <h2 class="mb-0 text-secondary">${summary.draft_pages || 0}</h2>
                                        <small class="text-muted">%${draftPercentage.toFixed(1)}</small>
                                    </div>
                                    <div class="text-secondary" style="font-size: 2.5rem; opacity: 0.2;">
                                        <i class="bi bi-file-earmark"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Son 7 Gün Güncelleme
                html += `
                    <div class="col-md-3 col-sm-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.75rem;">Son 7 Gün</h6>
                                        <h2 class="mb-0 text-info">${summary.recent_updates || 0}</h2>
                                        <small class="text-muted">Güncellenen</small>
                                    </div>
                                    <div class="text-info" style="font-size: 2.5rem; opacity: 0.2;">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                html += '</div>';

                // ========== MISSING META SECTION ==========
                if (missingMeta && missingMeta.total && missingMeta.total > 0) {
                    html += `
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> Eksik Meta Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                    `;

                    const missingFields = [{
                            key: 'missing_title',
                            label: 'Title',
                            icon: 'type',
                            color: 'danger'
                        },
                        {
                            key: 'missing_description',
                            label: 'Description',
                            icon: 'text-paragraph',
                            color: 'warning'
                        },
                        {
                            key: 'missing_og_image',
                            label: 'OG Image',
                            icon: 'image',
                            color: 'info'
                        },
                        {
                            key: 'missing_canonical',
                            label: 'Canonical URL',
                            icon: 'link-45deg',
                            color: 'secondary'
                        },
                        {
                            key: 'missing_jsonld',
                            label: 'JSON-LD',
                            icon: 'code-square',
                            color: 'primary'
                        },
                        {
                            key: 'missing_breadcrumb',
                            label: 'Breadcrumb',
                            icon: 'diagram-3',
                            color: 'success'
                        }
                    ];

                    missingFields.forEach(field => {
                        const count = missingMeta[field.key] || 0;
                        const percentage = missingMeta.missing_percentage?.[field.key.replace(
                            'missing_', '')] || 0;
                        const total = missingMeta.total || 1;
                        const progressPercentage = 100 - percentage;

                        html += `
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <i class="bi bi-${field.icon} text-${field.color}"></i>
                                        <strong>${field.label}</strong>
                                    </div>
                                    <div>
                                        <span class="badge bg-${field.color}">${count}</span>
                                        <small class="text-muted ms-2">%${percentage.toFixed(1)}</small>
                                    </div>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-${field.color}" role="progressbar" 
                                         style="width: ${progressPercentage}%"></div>
                                </div>
                                <small class="text-muted">${total - count}/${total} sayfa tamamlanmış</small>
                            </div>
                        `;
                    });

                    html += `
                                </div>
                            </div>
                        </div>
                    `;
                }

                // ========== DUPLICATE STATISTICS ==========
                if (duplicates.duplicate_count > 0) {
                    html += `
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-files text-danger"></i> Duplicate (Yinelenen) İçerikler</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h4 class="text-danger">${duplicates.duplicate_count || 0}</h4>
                                        <p class="text-muted mb-0">Yinelenen ${duplicates.type === 'title' ? 'Başlık' : 'Açıklama'}</p>
                                        <small class="text-muted">%${(duplicates.duplicate_percentage || 0).toFixed(1)}</small>
                                    </div>
                                    <div class="col-md-6">
                                        <h4 class="text-success">${duplicates.unique_count || 0}</h4>
                                        <p class="text-muted mb-0">Benzersiz ${duplicates.type === 'title' ? 'Başlık' : 'Açıklama'}</p>
                                    </div>
                                </div>
                    `;

                    // Duplicate groups preview
                    if (duplicates.duplicate_groups && duplicates.duplicate_groups.length > 0) {
                        html += `
                            <div class="mt-3">
                                <h6>En Çok Tekrar Eden ${duplicates.type === 'title' ? 'Başlıklar' : 'Açıklamalar'}:</h6>
                                <ul class="list-group list-group-flush">
                        `;

                        duplicates.duplicate_groups.slice(0, 5).forEach(group => {
                            html += `
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <small class="text-truncate" style="max-width: 70%;">${group.preview || group.value}</small>
                                    <span class="badge bg-danger rounded-pill">${group.count}</span>
                                </li>
                            `;
                        });

                        html += `
                                </ul>
                            </div>
                        `;
                    }

                    html += `
                            </div>
                        </div>
                    `;
                }

                // ========== DOMAIN BREAKDOWN ==========
                if (Object.keys(domainBreakdown).length > 0) {
                    html += `
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-globe text-${primaryColorClass}"></i> Domain Dağılımı</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Domain</th>
                                                <th class="text-end">Sayfa Sayısı</th>
                                                <th class="text-end" style="width: 150px;">Yüzde</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                    `;

                    const totalPagesForDomain = summary.total_pages || 1;
                    Object.entries(domainBreakdown).forEach(([domain, count]) => {
                        const domainPercentage = ((count / totalPagesForDomain) * 100).toFixed(1);
                        html += `
                            <tr>
                                <td><code>${domain}</code></td>
                                <td class="text-end"><strong>${count}</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                            <div class="progress-bar bg-${primaryColorClass}" 
                                                 role="progressbar" style="width: ${domainPercentage}%"></div>
                                        </div>
                                        <small class="text-muted" style="min-width: 45px;">%${domainPercentage}</small>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });

                    html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    `;
                }

                // Eğer hiç veri yoksa
                if (summary.total_pages === 0) {
                    html = `
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 4rem; opacity: 0.3;"></i>
                            <h4 class="mt-3 text-muted">Henüz sayfa eklenmemiş</h4>
                            <p class="text-muted">İstatistikleri görmek için önce sayfa ekleyin.</p>
                        </div>
                    `;
                }

                document.getElementById('statsContent').innerHTML = html;
            } catch (error) {
                console.error('Load Stats Error:', error);
                document.getElementById('statsContent').innerHTML =
                    '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> İstatistikler yüklenemedi: ' +
                    (error.message || 'Bilinmeyen hata') + '</div>';
            }
        }

        // ============================================
        // FORM MANAGEMENT
        // ============================================

        /**
         * Load form HTML with all fields
         * Bu fonksiyon tam form HTML'ini oluşturur ve pageFormContainer'a ekler.
         * Form tab-based yapıdadır: Basic, Meta Tags, Open Graph, Twitter Card, JSON-LD, Advanced
         */
        function loadForm() {
            const primaryClass = 'primary'; // CSS variable kullanılıyor
            const formHtml = `
            <form id="pageForm" onsubmit="savePage(event); return false;">
                <input type="hidden" id="pageId" name="id" value="">
                
                <ul class="nav nav-pills mb-3" id="formTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-form" type="button" role="tab">
                            <i class="bi bi-info-circle"></i> Temel Bilgiler
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="meta-tab" data-bs-toggle="tab" data-bs-target="#meta-form" type="button" role="tab">
                            <i class="bi bi-tags"></i> Meta Tags
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="og-tab" data-bs-toggle="tab" data-bs-target="#og-form" type="button" role="tab">
                            <i class="bi bi-facebook"></i> Open Graph
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="twitter-tab" data-bs-toggle="tab" data-bs-target="#twitter-form" type="button" role="tab">
                            <i class="bi bi-twitter"></i> Twitter Card
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="jsonld-tab" data-bs-toggle="tab" data-bs-target="#jsonld-form" type="button" role="tab">
                            <i class="bi bi-code-square"></i> JSON-LD Schema
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" data-bs-target="#advanced-form" type="button" role="tab">
                            <i class="bi bi-gear"></i> Gelişmiş
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="formTabContent">
                    <div class="tab-pane fade show active" id="basic-form" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="domain" class="form-label">Domain <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="domain" name="domain" required value="${window.location.hostname || '127.0.0.1'}">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Sayfanın ait olduğu domain adresidir. 
                                    <strong>Örnek:</strong> example.com, www.example.com. Çok domainli sitelerde önemlidir.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="path" class="form-label">Path <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="path" name="path" required value="/test-page" placeholder="/example">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Sayfanın URL yoludur (domain hariç). 
                                    <strong>Önemli:</strong> Mutlaka / ile başlamalıdır. Örnek: /urunler, /hakkimizda, /blog/yazi-1
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="draft">Draft</option>
                                </select>
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Sayfanın yayın durumunu belirler. 
                                    <strong>Active:</strong> Sayfa yayındadır ve meta tag'ler görüntülenir. 
                                    <strong>Draft:</strong> Sayfa taslaktadır, meta tag'ler görüntülenmez.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="language" class="form-label">Language</label>
                                <input type="text" class="form-control" id="language" name="language" value="tr" placeholder="tr">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Sayfa içeriğinin dilini belirtir (&lt;meta http-equiv="content-language"&gt;). 
                                    <strong>Örnek:</strong> tr (Türkçe), en (İngilizce), de (Almanca). Çok dilli siteler için önemlidir.
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required placeholder="Sayfa Başlığı (50-60 karakter ideal)" onblur="autoFillOGFields()">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Sayfanın ana başlığıdır ve &lt;title&gt; tag'inde görünür. 
                                    <strong>SEO Önemi:</strong> Google arama sonuçlarında görünen başlıktır, tıklanma oranını direkt etkiler. 
                                    <strong>Öneri:</strong> 50-60 karakter arası tutun, anahtar kelimeleri başa alın.
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Sayfa açıklaması (150-160 karakter ideal)" onblur="autoFillOGFields()"></textarea>
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Sayfa içeriğinin özet açıklamasıdır ve &lt;meta name="description"&gt; tag'inde görünür. 
                                    <strong>SEO Önemi:</strong> Google arama sonuçlarında başlığın altında görünen açıklamadır (snippet). 
                                    <strong>Öneri:</strong> 150-160 karakter arası tutun, okuyucuyu cezbedecek bir açıklama yazın.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="meta-form" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="keywords" class="form-label">Keywords</label>
                                <input type="text" class="form-control" id="keywords" name="keywords" placeholder="keyword1, keyword2, keyword3">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Sayfa ile ilgili anahtar kelimeleri belirtir (&lt;meta name="keywords"&gt;). 
                                    <strong>SEO Önemi:</strong> Modern SEO'da direkt etkisi düşüktür ancak içerik kategorilendirmesi için kullanılabilir. 
                                    <strong>Öneri:</strong> Virgülle ayrılmış 5-10 anahtar kelime yeterlidir. Spam yapmayın.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="robots" class="form-label">Robots</label>
                                <input type="text" class="form-control" id="robots" name="robots" value="index, follow" placeholder="index, follow">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Arama motorlarına sayfanın nasıl indexleneceğini söyler (&lt;meta name="robots"&gt;). 
                                    <strong>Değerler:</strong> index/noindex (indexlenip indexlenmeyeceği), follow/nofollow (linklerin takip edilip edilmeyeceği). 
                                    <strong>Örnek:</strong> "index, follow" (varsayılan), "noindex, nofollow" (arama motorlarında görünmesin).
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="canonical_url" class="form-label">Canonical URL</label>
                                <input type="url" class="form-control" id="canonical_url" name="canonical_url" placeholder="https://example.com/canonical">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Duplicate content sorununu çözmek için kullanılır (&lt;link rel="canonical"&gt;). 
                                    <strong>SEO Önemi:</strong> Çok önemli! Aynı içeriğin farklı URL'lerde göründüğü durumlarda ana URL'yi belirtir. 
                                    <strong>Öneri:</strong> Boş bırakılırsa otomatik olarak sayfa URL'si kullanılır.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="author" class="form-label">Author</label>
                                <input type="text" class="form-control" id="author" name="author" placeholder="Yazar / Firma Adı" onblur="autoFillFromAuthor()">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> İçeriğin yazarını veya sahibini belirtir (&lt;meta name="author"&gt;). 
                                    <strong>SEO Önemi:</strong> E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) için kritik. Google güvenilirlik sinyali olarak kullanır. 
                                    <strong>Öneri:</strong> Blog yazıları, makaleler ve içerik sayfalarında mutlaka belirtin.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="theme_color" class="form-label">Theme Color</label>
                                <input type="text" class="form-control" id="theme_color" name="theme_color" value="#0d6efd" placeholder="#0d6efd">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> PWA (Progressive Web App) ve mobil tarayıcılarda tema rengini belirler (&lt;meta name="theme-color"&gt;). 
                                    <strong>Kullanım:</strong> Mobil tarayıcı üst çubuğunun rengini belirler. 
                                    <strong>Format:</strong> Hex renk kodu (#RRGGBB), örnek: #0d6efd (mavi), #ff0000 (kırmızı).
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="og-form" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="og_site_name" class="form-label">OG Site Name</label>
                                <input type="text" class="form-control" id="og_site_name" name="og_site_name" placeholder="Site Adı">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Open Graph protokolünde sitenin adını belirtir (&lt;meta property="og:site_name"&gt;). 
                                    <strong>Kullanım:</strong> Facebook, LinkedIn gibi sosyal medya platformlarında paylaşım yapılırken görünür. 
                                    <strong>Öneri:</strong> Tüm sayfalarda aynı site adını kullanın (genelde config'de tanımlanır).
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="og_title" class="form-label">OG Title</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="og_title" name="og_title" placeholder="Open Graph başlığı">
                                    <button type="button" class="btn btn-outline-secondary" onclick="copyToOGTitle()" title="Title'dan Kopyala">
                                        <i class="bi bi-arrow-down-circle"></i> Title'dan Kopyala
                                    </button>
                                </div>
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Sosyal medya paylaşımlarında görünen başlıktır (&lt;meta property="og:title"&gt;). 
                                    <strong>SEO Önemi:</strong> Facebook, Twitter, LinkedIn gibi platformlarda görünürlük için kritik. 
                                    <strong>Öneri:</strong> Boş bırakılırsa otomatik olarak Title kullanılır. Sosyal medya için 60 karakter ideal. 
                                    <strong>Not:</strong> "Title'dan Kopyala" butonu ile Title'daki değeri otomatik kopyalayabilirsiniz.
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="og_description" class="form-label">OG Description</label>
                                <div class="input-group">
                                    <textarea class="form-control" id="og_description" name="og_description" rows="3" placeholder="Open Graph açıklaması"></textarea>
                                    <button type="button" class="btn btn-outline-secondary align-self-start" onclick="copyToOGDescription()" title="Description'dan Kopyala">
                                        <i class="bi bi-arrow-down-circle"></i> Desc'den Kopyala
                                    </button>
                                </div>
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Sosyal medya paylaşımlarında görünen açıklamadır (&lt;meta property="og:description"&gt;). 
                                    <strong>SEO Önemi:</strong> Paylaşım kartında görünen açıklama, tıklanma oranını etkiler. 
                                    <strong>Öneri:</strong> Boş bırakılırsa otomatik olarak Description kullanılır. 155 karakter ideal. 
                                    <strong>Not:</strong> "Desc'den Kopyala" butonu ile Description'daki değeri otomatik kopyalayabilirsiniz.
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="og_image" class="form-label">OG Image <span class="text-danger">* (ÇOK ÖNEMLİ)</span></label>
                                <input type="url" class="form-control" id="og_image" name="og_image" placeholder="https://example.com/image.jpg">
                                <div class="form-text text-danger">
                                    <strong>Ne İşe Yarar?</strong> Sosyal medya paylaşımlarında görünen görseldir (&lt;meta property="og:image"&gt;). 
                                    <strong>SEO Önemi:</strong> Çok kritik! Paylaşım kartında görünen görsel, engagement oranını direkt etkiler. 
                                    <strong>Öneri:</strong> 1200x630px boyutunda, yüksek kaliteli görsel kullanın. Mutlaka https:// ile erişilebilir olmalı. 
                                    <strong>Uyarı:</strong> Görsel olmadan paylaşım çekici olmaz, mutlaka ekleyin!
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="twitter-form" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="twitter_card" class="form-label">Twitter Card Type</label>
                                <select class="form-select" id="twitter_card" name="twitter_card">
                                    <option value="summary_large_image">summary_large_image</option>
                                    <option value="summary">summary</option>
                                    <option value="app">app</option>
                                    <option value="player">player</option>
                                </select>
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Twitter paylaşımlarında kartın görünüm tipini belirler (&lt;meta name="twitter:card"&gt;). 
                                    <strong>summary_large_image:</strong> Büyük görsel ile kart (önerilen). <strong>summary:</strong> Küçük görsel ile kart. 
                                    <strong>app/player:</strong> Özel içerikler için.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="twitter_site" class="form-label">Twitter Site (@username)</label>
                                <input type="text" class="form-control" id="twitter_site" name="twitter_site" placeholder="@sitehesabi">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Sitenin resmi Twitter hesabını belirtir (&lt;meta name="twitter:site"&gt;). 
                                    <strong>Kullanım:</strong> Twitter paylaşımlarında "via @sitehesabi" şeklinde görünür. 
                                    <strong>Öneri:</strong> @ işareti ile birlikte yazın: @example
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="twitter_creator" class="form-label">Twitter Creator (@username)</label>
                                <input type="text" class="form-control" id="twitter_creator" name="twitter_creator" placeholder="@sitehesabi">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> İçeriğin yaratıcısının Twitter hesabını belirtir (&lt;meta name="twitter:creator"&gt;). 
                                    <strong>Kullanım:</strong> Blog yazıları, makaleler gibi yazar bazlı içeriklerde yazarın Twitter hesabını gösterir. 
                                    <strong>Öneri:</strong> @ işareti ile birlikte yazın: @yazaradi
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="twitter_title" class="form-label">Twitter Title</label>
                                <input type="text" class="form-control" id="twitter_title" name="twitter_title" placeholder="Twitter başlığı">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Twitter paylaşımlarında görünen başlıktır (&lt;meta name="twitter:title"&gt;). 
                                    <strong>Öneri:</strong> Boş bırakılırsa sırayla og_title, title kullanılır. Twitter için 70 karakter ideal.
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="twitter_description" class="form-label">Twitter Description</label>
                                <textarea class="form-control" id="twitter_description" name="twitter_description" rows="3" placeholder="Twitter açıklaması"></textarea>
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Twitter paylaşımlarında görünen açıklamadır (&lt;meta name="twitter:description"&gt;). 
                                    <strong>Öneri:</strong> Boş bırakılırsa sırayla og_description, description kullanılır. Twitter için 200 karakter ideal.
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="twitter_image" class="form-label">Twitter Image</label>
                                <input type="url" class="form-control" id="twitter_image" name="twitter_image" placeholder="https://example.com/twitter-image.jpg">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> Twitter paylaşımlarında görünen görseldir (&lt;meta name="twitter:image"&gt;). 
                                    <strong>Öneri:</strong> Boş bırakılırsa og_image kullanılır. Twitter için 1200x675px boyutunda görsel ideal. 
                                    <strong>Not:</strong> Genelde og_image ile aynı görsel kullanılır, özel bir Twitter görseli gerekmez.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="jsonld-form" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label">
                                <strong>JSON-LD Schema Blokları</strong>
                                <button type="button" class="btn btn-sm btn-${primaryClass} ms-2" onclick="addSchemaBlock()">
                                    <i class="bi bi-plus-circle"></i> Yeni Schema Ekle
                                </button>
                            </label>
                            <div class="form-text mb-3">
                                Birden fazla JSON-LD schema ekleyebilirsiniz (Article, Product, FAQ, BreadcrumbList, Organization, WebSite, vb.)
                            </div>
                            <div id="schemaBlocksContainer">
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="advanced-form" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="query_hash" class="form-label">Query Hash</label>
                                <input type="text" class="form-control" id="query_hash" name="query_hash" placeholder="Otomatik oluşturulur (boş bırakın)">
                                <div class="form-text">
                                    <strong>Ne İşe Yarar?</strong> URL'deki query parametrelerinin (örn: ?category=elektronik&sort=price) hash'idir. 
                                    <strong>Kullanım:</strong> Filtreleme, sıralama gibi query parametreli sayfalar için aynı path'e farklı meta tanımlamak için kullanılır. 
                                    <strong>Öneri:</strong> Genelde boş bırakılır, sistem otomatik oluşturur. Sadece özel durumlarda manuel girin.
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i> <strong>İpucu:</strong> Bu alanlar genelde otomatik yönetilir. Sadece özel durumlarda düzenleyin.
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-${primaryClass}" id="saveBtn">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="bi bi-arrow-counterclockwise"></i> Sıfırla
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="cancelForm()">
                        <i class="bi bi-x-circle"></i> İptal / Geri Dön
                    </button>
                </div>
            </form>
        `;

            document.getElementById('pageFormContainer').innerHTML = formHtml;

            // Initialize schema blocks (add one empty block)
            if (typeof loadSchemaBlocks === 'function') {
                loadSchemaBlocks([]);
            }
        }

        /**
         * Edit page - Load page data into form
         * @param {number} id - Page ID
         */
        async function editPage(id) {
            try {
                // Switch to form tab
                const formTab = new bootstrap.Tab(document.getElementById('form-tab'));
                formTab.show();

                // Ensure form is loaded
                if (!document.getElementById('pageForm')) {
                    loadForm();
                    // Wait a bit for form to render
                    await new Promise(resolve => setTimeout(resolve, 100));
                }

                // Load page data
                const response = await apiCall(`/pages/${id}?include_seo_score=true`);
                const data = response.data || response;

                // Populate form with data
                document.getElementById('pageId').value = data.id || '';
                document.getElementById('domain').value = data.domain || '';
                document.getElementById('path').value = data.path || '';
                document.getElementById('title').value = data.title || '';
                document.getElementById('description').value = data.description || '';
                document.getElementById('status').value = data.status || 'active';
                document.getElementById('language').value = data.language || 'tr';
                document.getElementById('keywords').value = data.keywords || '';
                document.getElementById('robots').value = data.robots || 'index, follow';
                document.getElementById('canonical_url').value = data.canonical_url || '';
                document.getElementById('author').value = data.author || '';
                document.getElementById('theme_color').value = data.theme_color || '#0d6efd';
                document.getElementById('og_site_name').value = data.og_site_name || '';
                document.getElementById('og_title').value = data.og_title || '';
                document.getElementById('og_description').value = data.og_description || '';
                document.getElementById('og_image').value = data.og_image || '';
                document.getElementById('twitter_card').value = data.twitter_card || 'summary_large_image';
                document.getElementById('twitter_site').value = data.twitter_site || '';
                document.getElementById('twitter_creator').value = data.twitter_creator || '';
                document.getElementById('twitter_title').value = data.twitter_title || '';
                document.getElementById('twitter_description').value = data.twitter_description || '';
                document.getElementById('twitter_image').value = data.twitter_image || '';
                document.getElementById('query_hash').value = data.query_hash || '';

                // Update save button text
                document.getElementById('saveBtn').innerHTML = '<i class="bi bi-save"></i> Güncelle';

                // Load JSON-LD schema blocks
                if (data.jsonld) {
                    const jsonldArray = Array.isArray(data.jsonld) ? data.jsonld : [data.jsonld];
                    if (typeof loadSchemaBlocks === 'function') {
                        loadSchemaBlocks(jsonldArray);
                    }
                } else if (typeof loadSchemaBlocks === 'function') {
                    loadSchemaBlocks([]);
                }

                showMessage('Sayfa yüklendi', 'success');
            } catch (error) {
                showMessage('Sayfa yüklenemedi: ' + error.message, 'error');
            }
        }

        /**
         * Reset form to default state
         */
        function resetForm() {
            if (!document.getElementById('pageForm')) {
                return;
            }

            document.getElementById('pageForm').reset();
            document.getElementById('pageId').value = '';
            document.getElementById('saveBtn').innerHTML = '<i class="bi bi-save"></i> Kaydet';
            document.getElementById('domain').value = window.location.hostname || '127.0.0.1';
            document.getElementById('path').value = '/test-page';
            document.getElementById('status').value = 'active';
            document.getElementById('language').value = 'tr';
            document.getElementById('robots').value = 'index, follow';
            document.getElementById('twitter_card').value = 'summary_large_image';
            document.getElementById('theme_color').value = '#0d6efd';

            // Reset schema blocks
            if (typeof loadSchemaBlocks === 'function') {
                loadSchemaBlocks([]);
            }

            // Switch to basic tab
            const basicTab = new bootstrap.Tab(document.getElementById('basic-tab'));
            basicTab.show();
        }

        /**
         * Save page (CREATE or UPDATE)
         * @param {Event} e - Form submit event
         */
        async function savePage(e) {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            // Get ID and remove from data
            const id = data.id;
            delete data.id;

            // Validate required fields
            if (!data.domain || !data.path || !data.title) {
                showMessage('Domain, Path ve Title alanları zorunludur!', 'error');
                return;
            }

            // Collect JSON-LD from schema blocks
            if (typeof collectJsonLdBlocks === 'function') {
                const jsonldArray = collectJsonLdBlocks();
                if (jsonldArray.length > 0) {
                    data.jsonld = jsonldArray;
                } else {
                    delete data.jsonld;
                }
            }

            // Remove empty fields
            Object.keys(data).forEach(key => {
                if (data[key] === '' || data[key] === null || data[key] === undefined) {
                    delete data[key];
                }
            });

            try {
                const saveBtn = document.getElementById('saveBtn');
                const originalHtml = saveBtn.innerHTML;
                saveBtn.disabled = true;
                saveBtn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span>Kaydediliyor...';

                let result;
                if (id && id !== '') {
                    result = await apiCall(`/pages/${id}`, 'PUT', data);
                    showMessage('Sayfa başarıyla güncellendi', 'success');
                } else {
                    result = await apiCall('/pages', 'POST', data);
                    showMessage('Sayfa başarıyla oluşturuldu', 'success');
                }

                // Reset form
                resetForm();

                // Reload pages list
                loadPages(1);

                // Switch to list tab
                const listTab = new bootstrap.Tab(document.getElementById('list-tab'));
                listTab.show();
            } catch (error) {
                console.error('Save Page Error:', error);
                let errorMsg = error.message || 'Bilinmeyen hata';

                if (error.data && error.data.errors) {
                    const validationErrors = Object.values(error.data.errors).flat().join(', ');
                    errorMsg = 'Validation hatası: ' + validationErrors;
                } else if (error.data && error.data.message) {
                    errorMsg = error.data.message;
                }

                showMessage('Hata: ' + errorMsg, 'error');
            } finally {
                const saveBtn = document.getElementById('saveBtn');
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = id && id !== '' ? '<i class="bi bi-save"></i> Güncelle' :
                        '<i class="bi bi-save"></i> Kaydet';
                }
            }
        }

        /**
         * Delete page
         * @param {number} id - Page ID
         */
        async function deletePage(id) {
            if (!confirm('Bu sayfayı silmek istediğinize emin misiniz?')) {
                return;
            }

            try {
                await apiCall(`/pages/${id}`, 'DELETE');
                showMessage('Sayfa başarıyla silindi', 'success');
                loadPages(currentPage);
            } catch (error) {
                showMessage('Silme hatası: ' + error.message, 'error');
            }
        }

        // ============================================
        // AUTO-FILL FUNCTIONS
        // ============================================

        /**
         * Auto-fill OG (Open Graph) fields from Title and Description
         * Title ve Description doldurulduğunda OG Title ve OG Description'ı otomatik doldurur
         * Sadece OG alanları boşsa doldurur, kullanıcı manuel girdiyse değiştirmez
         */
        function autoFillOGFields() {
            const titleEl = document.getElementById('title');
            const descriptionEl = document.getElementById('description');
            const ogTitleEl = document.getElementById('og_title');
            const ogDescriptionEl = document.getElementById('og_description');

            if (!titleEl || !descriptionEl || !ogTitleEl || !ogDescriptionEl) {
                return; // Form henüz yüklenmemiş
            }

            // OG Title'ı doldur (sadece boşsa)
            if (titleEl.value.trim() && !ogTitleEl.value.trim()) {
                ogTitleEl.value = titleEl.value;
                // Kullanıcıya bilgi ver (opsiyonel, çok rahatsız edici olmasın)
                // showMessage('OG Title otomatik dolduruldu', 'info');
            }

            // OG Description'ı doldur (sadece boşsa)
            if (descriptionEl.value.trim() && !ogDescriptionEl.value.trim()) {
                ogDescriptionEl.value = descriptionEl.value;
                // showMessage('OG Description otomatik dolduruldu', 'info');
            }
        }

        /**
         * Copy Title to OG Title
         * Title'dan OG Title'a kopyalama butonu için
         */
        function copyToOGTitle() {
            const titleEl = document.getElementById('title');
            const ogTitleEl = document.getElementById('og_title');

            if (titleEl && ogTitleEl && titleEl.value.trim()) {
                ogTitleEl.value = titleEl.value;
                showMessage('Title, OG Title\'a kopyalandı', 'success');
            } else {
                showMessage('Önce Title alanını doldurun', 'warning');
            }
        }

        /**
         * Copy Description to OG Description
         * Description'dan OG Description'a kopyalama butonu için
         */
        function copyToOGDescription() {
            const descriptionEl = document.getElementById('description');
            const ogDescriptionEl = document.getElementById('og_description');

            if (descriptionEl && ogDescriptionEl && descriptionEl.value.trim()) {
                ogDescriptionEl.value = descriptionEl.value;
                showMessage('Description, OG Description\'a kopyalandı', 'success');
            } else {
                showMessage('Önce Description alanını doldurun', 'warning');
            }
        }

        /**
         * Auto-fill fields based on Author
         * Author adına göre bazı alanları otomatik doldurur
         * Örnek: Author varsa Twitter Creator'a da eklenebilir
         */
        function autoFillFromAuthor() {
            const authorEl = document.getElementById('author');
            const twitterCreatorEl = document.getElementById('twitter_creator');

            if (!authorEl || !twitterCreatorEl) {
                return; // Form henüz yüklenmemiş
            }

            const authorValue = authorEl.value.trim();

            // Eğer author girilmişse ve Twitter Creator boşsa, Twitter formatına çevir
            // Not: Bu otomatik çevirme basit bir mantık, kullanıcı manuel düzenleyebilir
            if (authorValue && !twitterCreatorEl.value.trim()) {
                // Eğer author zaten @ ile başlıyorsa direkt kopyala
                if (authorValue.startsWith('@')) {
                    twitterCreatorEl.value = authorValue;
                } else {
                    // Değilse, kullanıcı manuel düzenlemek isteyebilir, bu yüzden otomatik doldurmayalım
                    // Ya da basit bir format önerisi yapabiliriz
                    // twitterCreatorEl.value = '@' + authorValue.toLowerCase().replace(/\s+/g, '');
                    // showMessage('Twitter Creator için @ ile başlayan format gerekir, lütfen manuel düzenleyin', 'info');
                }
            }
        }

        // ============================================
        // JSON-LD SCHEMA BLOCKS MANAGEMENT
        // ============================================

        /**
         * Schema block counter (for unique IDs)
         */
        let schemaBlockCounter = 0;

        /**
         * Add a new schema block
         * @param {string} schemaType - Schema type (optional)
         * @param {string} jsonContent - JSON content (optional)
         */
        function addSchemaBlock(schemaType = '', jsonContent = '') {
            const container = document.getElementById('schemaBlocksContainer');
            if (!container) {
                console.error('Schema blocks container not found');
                return;
            }

            const blockId = `schema-block-${schemaBlockCounter++}`;

            const schemaTypes = [
                '', 'Article', 'BlogPosting', 'Product', 'FAQPage', 'BreadcrumbList',
                'Organization', 'WebSite', 'WebPage', 'Person', 'LocalBusiness',
                'Recipe', 'VideoObject', 'Review', 'Event', 'Course', 'Custom'
            ];

            const primaryClass = PRIMARY_COLOR_CLASS;
            const blockHtml = `
            <div id="${blockId}" class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Schema Blok #${schemaBlockCounter}</strong>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeSchemaBlock('${blockId}')">
                        <i class="bi bi-trash"></i> Sil
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">
                            Schema Type
                            <button type="button" class="btn btn-sm btn-${primaryClass} ms-2" onclick="loadSchemaTemplate('${blockId}')">
                                <i class="bi bi-file-earmark-text"></i> Template Yükle
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info ms-2" onclick="showSchemaInfo('${blockId}')">
                                <i class="bi bi-info-circle"></i> Bilgi
                            </button>
                        </label>
                        <select class="form-select schema-type" onchange="onSchemaTypeChange('${blockId}')">
                            ${schemaTypes.map(type => 
                                `<option value="${type}" ${schemaType === type ? 'selected' : ''}>${type || 'Seçiniz (veya Custom)'}</option>`
                            ).join('')}
                        </select>
                        <div class="form-text">Schema.org type seçin ve "Template Yükle" butonuna tıklayın</div>
                    </div>
                    <div id="${blockId}-info" class="schema-info mb-3" style="display: none;">
                        <!-- Schema açıklaması buraya gelecek -->
                    </div>
                    <div class="mb-3">
                        <label class="form-label">JSON-LD Content</label>
                        <textarea class="form-control schema-json" rows="8" style="font-family: monospace; font-size: 12px;" placeholder='{"@context":"https://schema.org","@type":"Article","headline":"Başlık"}'>${jsonContent}</textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="formatSchemaJSON('${blockId}')">
                            <i class="bi bi-code"></i> JSON Formatla
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="previewSchemaBlock('${blockId}')">
                            <i class="bi bi-eye"></i> Önizle
                        </button>
                    </div>
                </div>
            </div>
        `;

            container.insertAdjacentHTML('beforeend', blockHtml);

            // Auto-load template if type is selected and content is empty
            if (schemaType && schemaType !== '' && schemaType !== 'Custom' && !jsonContent.trim()) {
                setTimeout(() => loadSchemaTemplate(blockId), 100);
            }
        }

        /**
         * Remove a schema block
         * @param {string} blockId - Block ID
         */
        function removeSchemaBlock(blockId) {
            if (confirm('Bu schema bloğunu silmek istediğinize emin misiniz?')) {
                const block = document.getElementById(blockId);
                if (block) {
                    block.remove();
                }
            }
        }

        /**
         * Schema type changed
         * @param {string} blockId - Block ID
         */
        function onSchemaTypeChange(blockId) {
            const block = document.getElementById(blockId);
            if (!block) return;

            const textarea = block.querySelector('.schema-json');
            const select = block.querySelector('.schema-type');
            const selectedType = select.value;
            const hasContent = textarea.value.trim().length > 0;

            // Update @type in JSON if content exists
            if (hasContent) {
                try {
                    const json = JSON.parse(textarea.value);
                    if (selectedType && selectedType !== 'Custom' && selectedType !== '') {
                        json['@type'] = selectedType;
                        textarea.value = JSON.stringify(json, null, 2);
                    }
                } catch (e) {
                    // JSON parse error, continue
                }
            }

            // Hide info when type changes
            const infoContainer = block.querySelector('.schema-info');
            if (infoContainer) {
                infoContainer.style.display = 'none';
            }
        }

        /**
         * Format JSON in a schema block
         * @param {string} blockId - Block ID
         */
        function formatSchemaJSON(blockId) {
            const block = document.getElementById(blockId);
            if (!block) return;

            const textarea = block.querySelector('.schema-json');
            try {
                const json = JSON.parse(textarea.value);
                textarea.value = JSON.stringify(json, null, 2);
                showMessage('JSON formatlandı', 'success');
            } catch (e) {
                showMessage('Geçersiz JSON formatı: ' + e.message, 'error');
            }
        }

        /**
         * Preview a schema block
         * @param {string} blockId - Block ID
         */
        function previewSchemaBlock(blockId) {
            const block = document.getElementById(blockId);
            if (!block) return;

            const textarea = block.querySelector('.schema-json');
            try {
                const json = JSON.parse(textarea.value);
                alert('Schema Önizleme:\\n\\n' + JSON.stringify(json, null, 2));
            } catch (e) {
                showMessage('Geçersiz JSON formatı: ' + e.message, 'error');
            }
        }

        /**
         * Get schema template info (açıklamalar ve kullanım rehberi)
         * @param {string} type - Schema type
         * @returns {object|null} - Info object or null
         */
        function getSchemaTemplateInfo(type) {
            const info = {
                'Article': {
                    title: 'Article - Makale Schema',
                    description: 'Haberler, blog yazıları, makaleler ve benzeri metin içerikler için kullanılır.',
                    useCases: [
                        'Blog yazıları',
                        'Haber makaleleri',
                        'Dergi makaleleri',
                        'Gazete yazıları',
                        'İçerik sayfaları'
                    ],
                    seoImportance: 'Yüksek - Google\'ın haber ve blog içeriklerini daha iyi anlamasına yardımcı olur. Rich Snippets için önemlidir.',
                    recommendations: [
                        'Mutlaka headline, author, datePublished ve image ekleyin',
                        'dateModified alanını da ekleyerek içeriğin güncel olduğunu gösterin',
                        'author bilgisi Person schema ile detaylandırılabilir',
                        'Her makale sayfasında bir kez kullanın'
                    ],
                    examplePages: 'Blog detay sayfaları, haber detay sayfaları, içerik sayfaları'
                },
                'BlogPosting': {
                    title: 'BlogPosting - Blog Yazısı Schema',
                    description: 'Article\'ın alt tipi, blog yazıları için özelleştirilmiş.',
                    useCases: [
                        'Blog yazıları',
                        'Kişisel blog gönderileri',
                        'Şirket blog yazıları'
                    ],
                    seoImportance: 'Yüksek - Blog içerikleri için Article\'dan daha spesifik.',
                    recommendations: [
                        'Article ile aynı alanları kullanın',
                        'Blog sayfaları için Article yerine BlogPosting tercih edin',
                        'author bilgisi çok önemlidir'
                    ],
                    examplePages: 'Blog detay sayfaları'
                },
                'Product': {
                    title: 'Product - Ürün Schema',
                    description: 'E-ticaret sitelerinde ürün sayfaları için kritik öneme sahip schema. Rich Snippets ve Google Shopping için zorunludur.',
                    useCases: [
                        'E-ticaret ürün sayfaları',
                        'Ürün detay sayfaları',
                        'Katalog sayfaları'
                    ],
                    seoImportance: 'Çok Yüksek - Google Shopping ve ürün aramalarında görünürlüğü artırır. Fiyat, stok durumu, değerlendirme gibi bilgileri gösterir.',
                    recommendations: [
                        'Mutlaka offers (fiyat, para birimi, stok durumu) ekleyin',
                        'image array olarak birden fazla görsel ekleyebilirsiniz',
                        'brand, gtin, mpn gibi alanlar ekleyerek ürünü benzersiz tanımlayın',
                        'aggregateRating (değerlendirme) ekleyerek Rich Snippets\'te yıldız gösterimi sağlayın',
                        'Her ürün sayfasında bir kez kullanın'
                    ],
                    examplePages: 'Ürün detay sayfaları, e-ticaret ürün sayfaları'
                },
                'FAQPage': {
                    title: 'FAQPage - Sık Sorulan Sorular Schema',
                    description: 'Sık sorulan sorular ve cevapları için kullanılır. Google\'ın FAQ Rich Snippets göstermesini sağlar.',
                    useCases: [
                        'SSS (Sık Sorulan Sorular) sayfaları',
                        'Yardım sayfaları',
                        'Destek sayfaları'
                    ],
                    seoImportance: 'Yüksek - Google FAQ Rich Snippets gösterir, bu da tıklanma oranını artırır.',
                    recommendations: [
                        'mainEntity array\'inde en az 2-3 soru-cevap çifti olmalı',
                        'Her soru Question, her cevap Answer schema ile tanımlanmalı',
                        'En fazla 50 soru-cevap çifti önerilir',
                        'Sayfadaki içerikle schema içeriği tutarlı olmalı',
                        'Sadece gerçekten SSS sayfalarında kullanın'
                    ],
                    examplePages: 'SSS sayfaları, yardım sayfaları, destek sayfaları'
                },
                'BreadcrumbList': {
                    title: 'BreadcrumbList - Ekmek Kırıntısı Schema',
                    description: 'Sayfa hiyerarşisini gösteren navigasyon schema\'sı. Google\'ın breadcrumb navigasyon göstermesini sağlar.',
                    useCases: [
                        'Tüm iç sayfalar (özellikle kategori ve alt kategori sayfaları)',
                        'Ürün sayfaları',
                        'İçerik sayfaları'
                    ],
                    seoImportance: 'Orta-Yüksek - Breadcrumb navigasyonu arama sonuçlarında görüntülenir, kullanıcı deneyimini artırır.',
                    recommendations: [
                        'Ana sayfadan başlayarak tam hiyerarşiyi gösterin',
                        'Her sayfada bir kez kullanın (genelde tek seferlik)',
                        'position sıralaması 1\'den başlamalı ve artmalı',
                        'name ve item URL\'leri doğru olmalı'
                    ],
                    examplePages: 'Tüm iç sayfalar (kategori, ürün, blog, vb.)'
                },
                'Organization': {
                    title: 'Organization - Organizasyon Schema',
                    description: 'Şirket, kurum veya organizasyon bilgilerini tanımlar. Genelde ana sayfada veya hakkımızda sayfasında kullanılır.',
                    useCases: [
                        'Ana sayfa',
                        'Hakkımızda sayfası',
                        'İletişim sayfası',
                        'Footer (tüm sayfalarda)'
                    ],
                    seoImportance: 'Yüksek - Google Knowledge Graph için önemlidir. Şirket bilgilerini yapılandırır.',
                    recommendations: [
                        'Ana sayfada veya tüm sayfalarda (footer) kullanın',
                        'logo, contactPoint (iletişim), sameAs (sosyal medya) alanlarını ekleyin',
                        'Sadece bir kez (ana sayfa veya global) kullanın, her sayfada tekrar etmeyin',
                        'address alanı ile fiziksel adres ekleyebilirsiniz'
                    ],
                    examplePages: 'Ana sayfa (önerilen), hakkımızda sayfası'
                },
                'WebSite': {
                    title: 'WebSite - Web Sitesi Schema',
                    description: 'Tüm web sitesi için genel bilgiler. Genelde ana sayfada kullanılır, özellikle SiteLinks (Site Bağlantıları) için önemlidir.',
                    useCases: [
                        'Ana sayfa'
                    ],
                    seoImportance: 'Orta - SiteLinks görünümü için önemli. Google\'ın sitenizi daha iyi anlamasına yardımcı olur.',
                    recommendations: [
                        'Sadece ana sayfada kullanın',
                        'potentialAction ile SiteSearchBox ekleyerek site içi arama özelliğini belirtin',
                        'url alanını mutlaka ekleyin'
                    ],
                    examplePages: 'Ana sayfa (sadece)'
                },
                'WebPage': {
                    title: 'WebPage - Web Sayfası Schema',
                    description: 'Genel web sayfası schema\'sı. Spesifik bir schema yoksa bu kullanılabilir, ancak daha spesifik schema\'lar tercih edilmelidir.',
                    useCases: [
                        'Genel içerik sayfaları',
                        'Spesifik schema\'nın uygun olmadığı sayfalar'
                    ],
                    seoImportance: 'Düşük-Orta - Spesifik schema\'lar daha iyi olduğu için sadece gerektiğinde kullanın.',
                    recommendations: [
                        'Mümkünse Article, Product gibi daha spesifik schema\'lar kullanın',
                        'Sadece gerçekten uygun schema yoksa WebPage kullanın'
                    ],
                    examplePages: 'Genel içerik sayfaları'
                },
                'Person': {
                    title: 'Person - Kişi Schema',
                    description: 'Yazar, kurucu, CEO gibi kişiler için kullanılır. Genelde Article author veya Organization founder olarak nested kullanılır.',
                    useCases: [
                        'Yazar sayfaları',
                        'Ekip sayfaları',
                        'Hakkımızda sayfası (kurucu/CEO)'
                    ],
                    seoImportance: 'Orta - E-E-A-T (Expertise, Authoritativeness, Trustworthiness) için önemlidir.',
                    recommendations: [
                        'Article veya BlogPosting içinde author olarak kullanılabilir',
                        'image, jobTitle, sameAs (sosyal medya) alanlarını ekleyin',
                        'Bağımsız olarak yazar sayfalarında kullanılabilir'
                    ],
                    examplePages: 'Yazar sayfaları, ekip sayfaları'
                },
                'LocalBusiness': {
                    title: 'LocalBusiness - Yerel İşletme Schema',
                    description: 'Fiziksel bir konumu olan işletmeler için kullanılır. Google Maps ve yerel aramalar için kritiktir.',
                    useCases: [
                        'Restoran sayfaları',
                        'Mağaza sayfaları',
                        'Hizmet işletmeleri',
                        'İletişim/Hakkımızda sayfası'
                    ],
                    seoImportance: 'Çok Yüksek - Google Maps ve yerel aramalarda görünürlüğü artırır.',
                    recommendations: [
                        'address, telephone, priceRange alanlarını mutlaka ekleyin',
                        'openingHoursSpecification ile çalışma saatleri ekleyin',
                        'geoCoordinates (enlem/boylam) ekleyerek konumu belirtin',
                        'LocalBusiness alt tiplerini (Restaurant, Store, vb.) kullanabilirsiniz'
                    ],
                    examplePages: 'İletişim sayfası, mağaza sayfası, restoran sayfası'
                },
                'Recipe': {
                    title: 'Recipe - Tarif Schema',
                    description: 'Yemek tarifleri için özelleştirilmiş schema. Google\'da tarif Rich Snippets gösterir.',
                    useCases: [
                        'Yemek tarifi sayfaları',
                        'Yemek blogları'
                    ],
                    seoImportance: 'Yüksek - Tarif aramalarında Rich Snippets (resim, süre, kalori) gösterir.',
                    recommendations: [
                        'prepTime, cookTime, totalTime ekleyin',
                        'recipeYield (kaç kişilik) ekleyin',
                        'nutritionInformation (beslenme bilgisi) ekleyebilirsiniz',
                        'image mutlaka eklenmeli'
                    ],
                    examplePages: 'Yemek tarifi sayfaları'
                },
                'VideoObject': {
                    title: 'VideoObject - Video Schema',
                    description: 'Video içerikler için kullanılır. Google\'da video Rich Snippets gösterir.',
                    useCases: [
                        'Video sayfaları',
                        'YouTube embed sayfaları',
                        'Eğitim videoları'
                    ],
                    seoImportance: 'Yüksek - Video aramalarında thumbnail ve süre bilgisi gösterir.',
                    recommendations: [
                        'uploadDate, duration, thumbnailUrl ekleyin',
                        'embedUrl veya contentUrl ile video URL\'i ekleyin',
                        'description ve name mutlaka eklenmeli'
                    ],
                    examplePages: 'Video sayfaları, eğitim sayfaları'
                },
                'Review': {
                    title: 'Review - Değerlendirme Schema',
                    description: 'Ürün veya hizmet değerlendirmeleri için kullanılır. Rich Snippets\'te yıldız gösterimi sağlar.',
                    useCases: [
                        'Ürün değerlendirme sayfaları',
                        'Hizmet değerlendirme sayfaları',
                        'Product schema ile birlikte kullanılabilir'
                    ],
                    seoImportance: 'Yüksek - Yıldızlı değerlendirme görünümü tıklanma oranını artırır.',
                    recommendations: [
                        'author (Person), reviewRating (1-5 arası), reviewBody ekleyin',
                        'Product schema ile birlikte kullanılabilir',
                        'aggregateRating Product içinde daha yaygındır'
                    ],
                    examplePages: 'Ürün değerlendirme sayfaları'
                },
                'Event': {
                    title: 'Event - Etkinlik Schema',
                    description: 'Konser, konferans, workshop gibi etkinlikler için kullanılır.',
                    useCases: [
                        'Etkinlik sayfaları',
                        'Konser sayfaları',
                        'Konferans sayfaları'
                    ],
                    seoImportance: 'Orta-Yüksek - Google Events için görünürlüğü artırır.',
                    recommendations: [
                        'startDate, endDate, location (Place) mutlaka ekleyin',
                        'offers (bilet fiyatı) ekleyebilirsiniz',
                        'organizer (Organization veya Person) ekleyin'
                    ],
                    examplePages: 'Etkinlik detay sayfaları'
                },
                'Course': {
                    title: 'Course - Kurs Schema',
                    description: 'Eğitim kursları için kullanılır. Eğitim içeriklerini yapılandırır.',
                    useCases: [
                        'Online kurs sayfaları',
                        'Eğitim programı sayfaları',
                        'Sertifika programları'
                    ],
                    seoImportance: 'Orta-Yüksek - Eğitim aramalarında görünürlüğü artırır.',
                    recommendations: [
                        'courseCode, educationalCredentialAwarded ekleyin',
                        'provider (Organization) ekleyin',
                        'coursePrerequisites ekleyebilirsiniz'
                    ],
                    examplePages: 'Kurs detay sayfaları, eğitim sayfaları'
                }
            };

            return info[type] || null;
        }

        /**
         * Get schema template (detaylı ve SEO uyumlu)
         * @param {string} type - Schema type
         * @returns {object|null} - Template object or null
         */
        function getSchemaTemplate(type) {
            const today = new Date().toISOString().split('T')[0];

            const templates = {
                'Article': {
                    '@context': 'https://schema.org',
                    '@type': 'Article',
                    'headline': 'Makale Başlığı',
                    'description': 'Makale açıklaması',
                    'image': 'https://example.com/image.jpg',
                    'author': {
                        '@type': 'Person',
                        'name': 'Yazar Adı'
                    },
                    'datePublished': today,
                    'dateModified': today,
                    'publisher': {
                        '@type': 'Organization',
                        'name': 'Site Adı',
                        'logo': {
                            '@type': 'ImageObject',
                            'url': 'https://example.com/logo.png'
                        }
                    }
                },
                'BlogPosting': {
                    '@context': 'https://schema.org',
                    '@type': 'BlogPosting',
                    'headline': 'Blog Yazısı Başlığı',
                    'description': 'Blog yazısı açıklaması',
                    'image': 'https://example.com/image.jpg',
                    'author': {
                        '@type': 'Person',
                        'name': 'Yazar Adı'
                    },
                    'datePublished': today,
                    'dateModified': today,
                    'publisher': {
                        '@type': 'Organization',
                        'name': 'Site Adı',
                        'logo': {
                            '@type': 'ImageObject',
                            'url': 'https://example.com/logo.png'
                        }
                    }
                },
                'Product': {
                    '@context': 'https://schema.org',
                    '@type': 'Product',
                    'name': 'Ürün Adı',
                    'description': 'Ürün açıklaması',
                    'image': ['https://example.com/product-image.jpg'],
                    'brand': {
                        '@type': 'Brand',
                        'name': 'Marka Adı'
                    },
                    'offers': {
                        '@type': 'Offer',
                        'url': 'https://example.com/urun',
                        'priceCurrency': 'TRY',
                        'price': '0.00',
                        'availability': 'https://schema.org/InStock',
                        'priceValidUntil': new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split(
                            'T')[0]
                    }
                },
                'FAQPage': {
                    '@context': 'https://schema.org',
                    '@type': 'FAQPage',
                    'mainEntity': [{
                        '@type': 'Question',
                        'name': 'Soru 1?',
                        'acceptedAnswer': {
                            '@type': 'Answer',
                            'text': 'Cevap 1'
                        }
                    }, {
                        '@type': 'Question',
                        'name': 'Soru 2?',
                        'acceptedAnswer': {
                            '@type': 'Answer',
                            'text': 'Cevap 2'
                        }
                    }]
                },
                'BreadcrumbList': {
                    '@context': 'https://schema.org',
                    '@type': 'BreadcrumbList',
                    'itemListElement': [{
                        '@type': 'ListItem',
                        'position': 1,
                        'name': 'Ana Sayfa',
                        'item': 'https://example.com'
                    }, {
                        '@type': 'ListItem',
                        'position': 2,
                        'name': 'Kategori',
                        'item': 'https://example.com/kategori'
                    }]
                },
                'Organization': {
                    '@context': 'https://schema.org',
                    '@type': 'Organization',
                    'name': 'Organizasyon Adı',
                    'url': 'https://example.com',
                    'logo': 'https://example.com/logo.png',
                    'contactPoint': {
                        '@type': 'ContactPoint',
                        'telephone': '+90-XXX-XXX-XX-XX',
                        'contactType': 'customer service'
                    },
                    'sameAs': [
                        'https://www.facebook.com/example',
                        'https://www.twitter.com/example'
                    ]
                },
                'WebSite': {
                    '@context': 'https://schema.org',
                    '@type': 'WebSite',
                    'name': 'Site Adı',
                    'url': 'https://example.com',
                    'potentialAction': {
                        '@type': 'SearchAction',
                        'target': {
                            '@type': 'EntryPoint',
                            'urlTemplate': 'https://example.com/arama?q={search_term_string}'
                        },
                        'query-input': 'required name=search_term_string'
                    }
                },
                'WebPage': {
                    '@context': 'https://schema.org',
                    '@type': 'WebPage',
                    'name': 'Sayfa Adı',
                    'description': 'Sayfa açıklaması',
                    'url': 'https://example.com/sayfa'
                },
                'Person': {
                    '@context': 'https://schema.org',
                    '@type': 'Person',
                    'name': 'Kişi Adı',
                    'jobTitle': 'İş Ünvanı',
                    'image': 'https://example.com/person.jpg',
                    'sameAs': [
                        'https://www.linkedin.com/in/example',
                        'https://twitter.com/example'
                    ]
                },
                'LocalBusiness': {
                    '@context': 'https://schema.org',
                    '@type': 'LocalBusiness',
                    'name': 'İşletme Adı',
                    'address': {
                        '@type': 'PostalAddress',
                        'streetAddress': 'Cadde, Sokak, No',
                        'addressLocality': 'Şehir',
                        'addressRegion': 'İlçe',
                        'postalCode': '34000',
                        'addressCountry': 'TR'
                    },
                    'telephone': '+90-XXX-XXX-XX-XX',
                    'priceRange': '$$',
                    'openingHoursSpecification': [{
                        '@type': 'OpeningHoursSpecification',
                        'dayOfWeek': ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                        'opens': '09:00',
                        'closes': '18:00'
                    }]
                },
                'Recipe': {
                    '@context': 'https://schema.org',
                    '@type': 'Recipe',
                    'name': 'Tarif Adı',
                    'description': 'Tarif açıklaması',
                    'image': 'https://example.com/recipe.jpg',
                    'prepTime': 'PT15M',
                    'cookTime': 'PT30M',
                    'totalTime': 'PT45M',
                    'recipeYield': '4 kişilik',
                    'recipeIngredient': ['Malzeme 1', 'Malzeme 2'],
                    'recipeInstructions': [{
                        '@type': 'HowToStep',
                        'text': 'Adım 1'
                    }]
                },
                'VideoObject': {
                    '@context': 'https://schema.org',
                    '@type': 'VideoObject',
                    'name': 'Video Adı',
                    'description': 'Video açıklaması',
                    'thumbnailUrl': 'https://example.com/thumbnail.jpg',
                    'uploadDate': today,
                    'duration': 'PT5M30S',
                    'contentUrl': 'https://example.com/video.mp4',
                    'embedUrl': 'https://example.com/embed'
                },
                'Review': {
                    '@context': 'https://schema.org',
                    '@type': 'Review',
                    'itemReviewed': {
                        '@type': 'Product',
                        'name': 'Ürün Adı'
                    },
                    'reviewRating': {
                        '@type': 'Rating',
                        'ratingValue': '5',
                        'bestRating': '5'
                    },
                    'author': {
                        '@type': 'Person',
                        'name': 'Yorumcu Adı'
                    },
                    'reviewBody': 'Ürün hakkında yorum'
                },
                'Event': {
                    '@context': 'https://schema.org',
                    '@type': 'Event',
                    'name': 'Etkinlik Adı',
                    'description': 'Etkinlik açıklaması',
                    'startDate': today + 'T19:00',
                    'endDate': today + 'T21:00',
                    'location': {
                        '@type': 'Place',
                        'name': 'Etkinlik Mekanı',
                        'address': {
                            '@type': 'PostalAddress',
                            'addressLocality': 'Şehir'
                        }
                    },
                    'organizer': {
                        '@type': 'Organization',
                        'name': 'Organizatör Adı'
                    }
                },
                'Course': {
                    '@context': 'https://schema.org',
                    '@type': 'Course',
                    'name': 'Kurs Adı',
                    'description': 'Kurs açıklaması',
                    'provider': {
                        '@type': 'Organization',
                        'name': 'Eğitim Kurumu',
                        'sameAs': 'https://example.com'
                    },
                    'courseCode': 'KURS-001'
                }
            };

            return templates[type] || null;
        }

        /**
         * Show schema info/description
         * @param {string} blockId - Block ID
         */
        function showSchemaInfo(blockId) {
            const block = document.getElementById(blockId);
            if (!block) return;

            const select = block.querySelector('.schema-type');
            const infoContainer = block.querySelector('.schema-info');
            const selectedType = select.value;

            if (!selectedType || selectedType === '' || selectedType === 'Custom') {
                showMessage('Lütfen önce bir Schema Type seçin', 'warning');
                return;
            }

            const info = getSchemaTemplateInfo(selectedType);
            if (!info) {
                showMessage('Bu schema type için bilgi bulunamadı', 'warning');
                return;
            }

            const infoHtml = `
                <div class="alert alert-info">
                    <h6 class="alert-heading"><i class="bi bi-info-circle"></i> ${info.title}</h6>
                    <p class="mb-2"><strong>Açıklama:</strong> ${info.description}</p>
                    <p class="mb-2"><strong>SEO Önemi:</strong> ${info.seoImportance}</p>
                    <p class="mb-2"><strong>Kullanım Yerleri:</strong> ${info.examplePages}</p>
                    <div class="mb-2">
                        <strong>Önerilen Sayfa Türleri:</strong>
                        <ul class="mb-0">
                            ${info.useCases.map(useCase => `<li>${useCase}</li>`).join('')}
                        </ul>
                    </div>
                    <div class="mt-2">
                        <strong>Öneriler:</strong>
                        <ul class="mb-0">
                            ${info.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            `;

            infoContainer.innerHTML = infoHtml;
            infoContainer.style.display = infoContainer.style.display === 'none' ? 'block' : 'none';
        }

        /**
         * Load schema template into block
         * @param {string} blockId - Block ID
         */
        function loadSchemaTemplate(blockId) {
            const block = document.getElementById(blockId);
            if (!block) return;

            const select = block.querySelector('.schema-type');
            const textarea = block.querySelector('.schema-json');
            const selectedType = select.value;

            if (!selectedType || selectedType === '' || selectedType === 'Custom') {
                showMessage('Lütfen önce bir Schema Type seçin', 'warning');
                return;
            }

            const template = getSchemaTemplate(selectedType);
            if (!template) {
                showMessage('Bu schema type için template bulunamadı', 'warning');
                return;
            }

            // Ask confirmation if content exists
            if (textarea.value.trim()) {
                if (!confirm('Mevcut içerik silinecek ve template yüklenecek. Devam etmek istiyor musunuz?')) {
                    return;
                }
            }

            // Load template
            textarea.value = JSON.stringify(template, null, 2);

            // Show info after loading template
            const infoContainer = block.querySelector('.schema-info');
            if (infoContainer) {
                const info = getSchemaTemplateInfo(selectedType);
                if (info) {
                    const infoHtml = `
                        <div class="alert alert-success">
                            <h6 class="alert-heading"><i class="bi bi-check-circle"></i> ${info.title} Template Yüklendi</h6>
                            <p class="mb-2"><strong>Açıklama:</strong> ${info.description}</p>
                            <p class="mb-2"><strong>Kullanım Yerleri:</strong> ${info.examplePages}</p>
                            <p class="mb-0"><small><strong>💡 İpucu:</strong> "Bilgi" butonuna tıklayarak detaylı kullanım rehberini görebilirsiniz.</small></p>
                        </div>
                    `;
                    infoContainer.innerHTML = infoHtml;
                    infoContainer.style.display = 'block';
                }
            }

            showMessage(selectedType + ' template\'i yüklendi', 'success');
        }

        /**
         * Collect all schema blocks into JSON-LD array
         * @returns {Array} - Array of JSON-LD objects
         */
        function collectJsonLdBlocks() {
            const container = document.getElementById('schemaBlocksContainer');
            if (!container) return [];

            const blocks = container.querySelectorAll('.card');
            const jsonldArray = [];

            blocks.forEach(block => {
                const textarea = block.querySelector('.schema-json');
                if (!textarea) return;

                const jsonContent = textarea.value.trim();
                if (jsonContent) {
                    try {
                        const json = JSON.parse(jsonContent);
                        if (json && typeof json === 'object') {
                            jsonldArray.push(json);
                        }
                    } catch (e) {
                        console.error('Invalid JSON in schema block:', e);
                    }
                }
            });

            return jsonldArray;
        }

        /**
         * Load JSON-LD array into schema blocks
         * @param {Array} jsonldArray - Array of JSON-LD objects
         */
        function loadSchemaBlocks(jsonldArray) {
            const container = document.getElementById('schemaBlocksContainer');
            if (!container) return;

            container.innerHTML = '';
            schemaBlockCounter = 0;

            if (jsonldArray && Array.isArray(jsonldArray) && jsonldArray.length > 0) {
                jsonldArray.forEach(schema => {
                    const schemaType = schema['@type'] || '';
                    const jsonContent = JSON.stringify(schema, null, 2);
                    addSchemaBlock(schemaType, jsonContent);
                });
            } else {
                // Add one empty block
                addSchemaBlock();
            }
        }

        // Make functions available globally
        window.loadPages = loadPages;
        window.editPage = editPage;
        window.deletePage = deletePage;
        window.debounceSearch = debounceSearch;
        window.showMessage = showMessage;
        window.savePage = savePage;
        window.resetForm = resetForm;
        window.cancelForm = cancelForm;
        window.loadForm = loadForm;
        window.addSchemaBlock = addSchemaBlock;
        window.removeSchemaBlock = removeSchemaBlock;
        window.onSchemaTypeChange = onSchemaTypeChange;
        window.formatSchemaJSON = formatSchemaJSON;
        window.previewSchemaBlock = previewSchemaBlock;
        window.loadSchemaTemplate = loadSchemaTemplate;
        window.collectJsonLdBlocks = collectJsonLdBlocks;
        window.loadSchemaBlocks = loadSchemaBlocks;
        window.showSchemaInfo = showSchemaInfo;
        window.autoFillOGFields = autoFillOGFields;
        window.autoFillFromAuthor = autoFillFromAuthor;
        window.copyToOGTitle = copyToOGTitle;
        window.copyToOGDescription = copyToOGDescription;

    })();
</script>

{{-- 
    NOT: Bu component'in tam versiyonu çok büyük olacağı için,
    form yönetimi ve JSON-LD template'leri ayrı bir dosyaya taşınabilir.
    
    Şu an için temel yapı hazır. Devam ediyoruz...
--}}
