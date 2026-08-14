<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ config('app.name') }} API Docs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        #api-toc {
            max-width: 1460px;
            margin: 16px auto 0;
            padding: 16px 20px;
            font-family: sans-serif;
            border: 1px solid #d8d8d8;
            border-radius: 4px;
        }
        #api-toc h2 {
            margin: 0 0 8px;
            font-size: 1.1rem;
        }
        #api-toc-search {
            width: 100%;
            box-sizing: border-box;
            padding: 7px 10px;
            margin-bottom: 10px;
            border: 1px solid #d8d8d8;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        #api-toc-empty {
            display: none;
            padding: 8px 4px;
            color: #888;
            font-size: 0.85rem;
        }
        #api-toc-groups {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .toc-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border: 1px solid #d8d8d8;
            border-radius: 999px;
            background: #f7f7f7;
            color: #3b4151;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease;
        }
        .toc-chip:hover {
            background: #e8f0fe;
            border-color: #61affe;
        }
        .toc-chip .toc-chip-count {
            font-weight: 400;
            color: #888;
        }
        #go-to-top {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            background: #3b4151;
            color: #fff;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,.3);
            opacity: 0;
            visibility: hidden;
            transition: opacity .2s ease;
            z-index: 1000;
        }
        #go-to-top.visible {
            opacity: 1;
            visibility: visible;
        }
        #api-changelog {
            max-width: 1460px;
            margin: 12px auto 0;
            padding: 16px 20px;
            font-family: sans-serif;
            border: 1px solid #d8d8d8;
            border-radius: 4px;
        }
        #api-changelog h2 {
            margin: 0 0 8px;
            font-size: 1.1rem;
        }
        #api-changelog-filter {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 0.85rem;
            color: #3b4151;
            cursor: pointer;
            user-select: none;
        }
        #api-changelog-filter .switch {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 20px;
            flex-shrink: 0;
        }
        #api-changelog-filter .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        #api-changelog-filter .switch .slider {
            position: absolute;
            inset: 0;
            background: #ccc;
            border-radius: 999px;
            transition: background .15s ease;
        }
        #api-changelog-filter .switch .slider::before {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            left: 2px;
            top: 2px;
            background: #fff;
            border-radius: 50%;
            transition: transform .15s ease;
        }
        #api-changelog-filter .switch input:checked + .slider {
            background: #61affe;
        }
        #api-changelog-filter .switch input:checked + .slider::before {
            transform: translateX(16px);
        }
        #api-changelog-empty {
            display: none;
            padding: 8px 4px;
            color: #888;
            font-size: 0.85rem;
        }
        #api-changelog li.is-filtered {
            display: none;
        }
        #api-changelog details.is-filtered {
            display: none;
        }
        #api-changelog details {
            border-top: 1px solid #eee;
            padding: 8px 0;
        }
        #api-changelog details:first-of-type {
            border-top: none;
        }
        #api-changelog summary {
            cursor: pointer;
            font-weight: 600;
            color: #3b4151;
            font-size: 0.95rem;
        }
        #api-changelog summary .changelog-count {
            font-weight: 400;
            color: #888;
            font-size: 0.85rem;
        }
        #api-changelog ul {
            margin: 8px 0 4px;
            padding-left: 20px;
        }
        #api-changelog li {
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 6px;
        }
        #api-changelog code {
            background: #f0f0f0;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 0.82rem;
        }
    </style>
</head>
<body>
    <nav id="api-toc">
        <h2>Daftar Model API (per controller)</h2>
        <input type="search" id="api-toc-search" placeholder="Cari model... (contoh: transaksi, accounts)">
        <div id="api-toc-empty">Tidak ada model yang cocok.</div>
        <div id="api-toc-groups">Memuat daftar model&hellip;</div>
    </nav>

    <section id="api-changelog">
        <h2>Riwayat Perubahan API (grup per tanggal)</h2>

        <label id="api-changelog-filter">
            <span class="switch">
                <input type="checkbox" id="api-changelog-endpoint-only">
                <span class="slider"></span>
            </span>
            Hanya tampilkan update endpoint (baru/berubah)
        </label>
        <div id="api-changelog-empty">Tidak ada update endpoint pada rentang tanggal ini.</div>

        <details open data-date="2026-08-13">
            <summary>2026-08-13 <span class="changelog-count">5 commit</span></summary>
            <ul>
                <li data-endpoint="1"><code>059d30a</code> Add subscription payment entities: plans, requests, admin activation &mdash; <code>GET/POST /subscription-plans</code>, <code>GET/PUT/DELETE /subscription-plans/{id}</code>, <code>GET/POST /subscriptions</code>, <code>GET /subscriptions/{id}</code>, <code>GET /admin/subscriptions</code>, <code>POST /admin/subscriptions/{id}/activate</code>, <code>POST /admin/subscriptions/{id}/reject</code></li>
                <li data-endpoint="1"><code>e20cb3d</code> Close out P2: 401 untuk login gagal, filter index diperjelas, semantik header <code>X-Family-Id</code> diklarifikasi (perubahan kontrak pada endpoint yang sudah ada)</li>
                <li data-endpoint="1"><code>fff9f82</code> Localize validation messages, add transaction filters, income realization, and savings ETA &mdash; filter baru di <code>GET /transactions</code></li>
                <li data-endpoint="0"><code>6c513a5</code> Add thinking/error events to the chat SSE stream (perluasan event, bukan endpoint baru)</li>
                <li data-endpoint="1"><code>d3f75d5</code> Add chat attachment uploads and server-driven onboarding interview &mdash; <code>POST /uploads</code></li>
            </ul>
        </details>

        <details data-date="2026-08-11">
            <summary>2026-08-11 <span class="changelog-count">5 commit</span></summary>
            <ul>
                <li data-endpoint="1"><code>1c33638</code> Add ConfirmAiAction, confirm/reject endpoints, and the short-lived SSE stream &mdash; <code>GET /chat-threads/{id}/stream</code>, <code>POST /ai-actions/{id}/confirm</code>, <code>POST /ai-actions/{id}/reject</code></li>
                <li data-endpoint="1"><code>a24d54b</code> Add dynamic LLM settings and the core Alur AI pipeline &mdash; <code>GET/PUT /llm-settings</code></li>
                <li data-endpoint="0"><code>2d1f81f</code> Serve interactive API docs at / (halaman ini)</li>
                <li data-endpoint="1"><code>e2b0e97</code> Publish GET /api/v1/openapi.json (OpenAPI 3.0.3)</li>
                <li data-endpoint="1"><code>c18d375</code> Add GET /analytics/summary using v_wallet_month/v_cashflow_month</li>
            </ul>
        </details>

        <details data-date="2026-08-10">
            <summary>2026-08-10 <span class="changelog-count">4 commit</span></summary>
            <ul>
                <li data-endpoint="0"><code>27a7b19</code> Add feature tests, API-v1.md, and fix bugs the tests surfaced</li>
                <li data-endpoint="1"><code>ecf2b5d</code> Add POST /family-invites/accept to complete the invite flow</li>
                <li data-endpoint="1"><code>dc00a06</code> Add register/login/logout auth endpoints &mdash; <code>POST /auth/register</code>, <code>POST /auth/login</code>, <code>POST /auth/logout</code>, <code>GET /auth/me</code></li>
                <li data-endpoint="1"><code>8c6c1e5</code> Add CRUD API for all domain resources with multi-tenant isolation &mdash; families, family-members, family-invites, accounts, wallets, wallets.budgets, income-sources, savings-goals, transactions, recurring-rules, chat-threads, chat-threads.messages, onboarding-answers, notifications, ai-actions (index/show), audit-logs (index/show)</li>
            </ul>
        </details>
    </section>

    <div id="swagger-ui"></div>

    <button id="go-to-top" title="Kembali ke atas">&uarr;</button>

    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        const specUrl = '{{ url('/api/v1/openapi.json') }}';

        fetch(specUrl)
            .then((res) => res.json())
            .then((spec) => {
                renderToc(spec);
                window.ui = SwaggerUIBundle({
                    spec: spec,
                    dom_id: '#swagger-ui',
                    presets: [SwaggerUIBundle.presets.apis],
                });
            });

        function renderToc(spec) {
            const groups = document.getElementById('api-toc-groups');
            const tags = (spec.tags || []).map((t) => t.name);

            const operationsByTag = {};
            tags.forEach((tag) => { operationsByTag[tag] = []; });

            Object.entries(spec.paths || {}).forEach(([path, pathItem]) => {
                Object.entries(pathItem).forEach(([method, operation]) => {
                    if (!['get', 'post', 'put', 'patch', 'delete'].includes(method)) return;
                    const tag = (operation.tags || [])[0];
                    if (!tag || !operationsByTag[tag]) return;
                    operationsByTag[tag].push({ method, path, summary: operation.summary || '' });
                });
            });

            groups.innerHTML = '';

            tags.forEach((tag) => {
                const ops = operationsByTag[tag];
                if (!ops.length) return;

                const chip = document.createElement('a');
                chip.href = '#';
                chip.className = 'toc-chip';
                chip.dataset.search = [
                    tag,
                    ...ops.map((op) => `${op.method} ${op.path} ${op.summary}`),
                ].join(' ').toLowerCase();
                chip.innerHTML = `${tag} <span class="toc-chip-count">${ops.length}</span>`;

                chip.addEventListener('click', (e) => {
                    e.preventDefault();
                    scrollToOperation(ops[0].method, ops[0].path);
                });

                groups.appendChild(chip);
            });

            setupTocSearch();
        }

        function setupTocSearch() {
            const input = document.getElementById('api-toc-search');
            const groups = document.getElementById('api-toc-groups');
            const empty = document.getElementById('api-toc-empty');

            input.addEventListener('input', () => {
                const query = input.value.trim().toLowerCase();
                let anyVisible = false;

                groups.querySelectorAll('.toc-chip').forEach((chip) => {
                    const matches = query === '' || chip.dataset.search.includes(query);
                    chip.style.display = matches ? '' : 'none';
                    if (matches) anyVisible = true;
                });

                empty.style.display = anyVisible ? 'none' : 'block';
            });
        }

        // Matches the rendered Swagger UI block by method + path rather than
        // relying on swagger-ui's internal id/hash scheme, which varies across
        // versions and isn't set in this hand-written spec. The path comes
        // from the summary span's `data-path` attribute -- its textContent
        // isn't reliable since swagger-ui nests a deep-link icon inside it.
        function scrollToOperation(method, path) {
            const blocks = document.querySelectorAll('#swagger-ui .opblock');
            for (const block of blocks) {
                const blockMethod = block.querySelector('.opblock-summary-method')?.textContent.trim().toLowerCase();
                const blockPath = block.querySelector('.opblock-summary-path')?.getAttribute('data-path');
                if (blockMethod === method && blockPath === path) {
                    if (!block.classList.contains('is-open')) {
                        (block.querySelector('.opblock-summary-control') || block.querySelector('.opblock-summary'))?.click();
                    }
                    block.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    return;
                }
            }
        }

        function setupChangelogFilter() {
            const toggle = document.getElementById('api-changelog-endpoint-only');
            const empty = document.getElementById('api-changelog-empty');
            const dateGroups = document.querySelectorAll('#api-changelog details[data-date]');

            toggle.addEventListener('change', () => {
                const endpointOnly = toggle.checked;
                let anyGroupVisible = false;

                dateGroups.forEach((group) => {
                    const items = group.querySelectorAll('li');
                    let visibleCount = 0;

                    items.forEach((item) => {
                        const isEndpointUpdate = item.dataset.endpoint === '1';
                        const hide = endpointOnly && !isEndpointUpdate;
                        item.classList.toggle('is-filtered', hide);
                        if (!hide) visibleCount += 1;
                    });

                    const groupHidden = visibleCount === 0;
                    group.classList.toggle('is-filtered', groupHidden);
                    if (!groupHidden) anyGroupVisible = true;

                    group.querySelector('.changelog-count').textContent =
                        `${visibleCount} commit`;
                });

                empty.style.display = anyGroupVisible ? 'none' : 'block';
            });
        }
        setupChangelogFilter();

        const goToTopBtn = document.getElementById('go-to-top');
        window.addEventListener('scroll', () => {
            goToTopBtn.classList.toggle('visible', window.scrollY > 400);
        });
        goToTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</body>
</html>
