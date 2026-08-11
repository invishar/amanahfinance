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
    </style>
</head>
<body>
    <nav id="api-toc">
        <h2>Daftar Model API (per controller)</h2>
        <input type="search" id="api-toc-search" placeholder="Cari model... (contoh: transaksi, accounts)">
        <div id="api-toc-empty">Tidak ada model yang cocok.</div>
        <div id="api-toc-groups">Memuat daftar model&hellip;</div>
    </nav>

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
