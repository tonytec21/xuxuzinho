<style>
/* ========================================
   VARIÁVEIS DE CORES PARA TEMAS
   ======================================== */
:root {
    /* Cores base - Light Mode */
    --bg-primary: #ffffff;
    --bg-secondary: #f8f9fa;
    --bg-tertiary: #e9ecef;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --text-muted: #adb5bd;
    --border-color: #dee2e6;
    --shadow-sm: rgba(0, 0, 0, 0.08);
    --shadow-md: rgba(0, 0, 0, 0.15);
    --shadow-lg: rgba(0, 0, 0, 0.2);
    
    /* Cores dos cards de estatísticas - Light Mode */
    --stats-pendente-bg: #fff8e1;
    --stats-pendente-color: #f9a825;
    --stats-pendente-text: #f57c00;
    
    --stats-anotada-bg: #e8f5e9;
    --stats-anotada-color: #66bb6a;
    --stats-anotada-text: #2e7d32;
    
    --stats-recusada-bg: #ffebee;
    --stats-recusada-color: #ef5350;
    --stats-recusada-text: #c62828;
    
    --stats-total-bg: #e3f2fd;
    --stats-total-color: #42a5f5;
    --stats-total-text: #1565c0;
}

/* Dark Mode */
[data-bs-theme="dark"],
.dark-mode {
    --bg-primary: #212529;
    --bg-secondary: #343a40;
    --bg-tertiary: #495057;
    --text-primary: #f8f9fa;
    --text-secondary: #adb5bd;
    --text-muted: #6c757d;
    --border-color: #495057;
    --shadow-sm: rgba(0, 0, 0, 0.3);
    --shadow-md: rgba(0, 0, 0, 0.5);
    --shadow-lg: rgba(0, 0, 0, 0.7);
    
    /* Cores dos cards de estatísticas - Dark Mode */
    --stats-pendente-bg: #3e2f00;
    --stats-pendente-color: #ffc107;
    --stats-pendente-text: #ffeb3b;
    
    --stats-anotada-bg: #1b3a1b;
    --stats-anotada-color: #4caf50;
    --stats-anotada-text: #81c784;
    
    --stats-recusada-bg: #3a1f1f;
    --stats-recusada-color: #f44336;
    --stats-recusada-text: #ef5350;
    
    --stats-total-bg: #1a2b4a;
    --stats-total-color: #2196f3;
    --stats-total-text: #64b5f6;
}

/* ========================================
   COMPONENTES GERAIS
   ======================================== */

.filter-card {
    border: none;
    box-shadow: 0 0 20px var(--shadow-sm);
    border-radius: 12px;
    transition: all 0.3s ease;
    background-color: var(--bg-primary);
}

.filter-card:hover {
    box-shadow: 0 5px 25px var(--shadow-md);
}

.filter-header {
    background: var(--bg-primary);
    color: var(--text-primary);
    border-radius: 12px 12px 0 0;
    padding: 15px 20px;
    cursor: pointer;
    border: 1px solid var(--border-color);
    border-bottom: none;
    transition: all 0.3s ease;
}

.filter-header:hover {
    background: var(--bg-secondary);
}

.filter-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.1rem;
    color: var(--text-primary);
}

/* Dark mode específico para filter-header */
[data-bs-theme="dark"] .filter-header,
.dark-mode .filter-header {
    background: #3f4454;
    color: #e8eaed;
    border-color: #565b6f;
}

[data-bs-theme="dark"] .filter-header:hover,
.dark-mode .filter-header:hover {
    background: #4a4f63;
}

[data-bs-theme="dark"] .filter-header h5,
.dark-mode .filter-header h5 {
    color: #e8eaed;
}

.filter-body {
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 0 0 12px 12px;
    border: 1px solid var(--border-color);
    border-top: none;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid var(--border-color);
    padding: 10px 15px;
    transition: all 0.3s ease;
    background-color: var(--bg-primary);
    color: var(--text-primary);
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    background-color: var(--bg-primary);
    color: var(--text-primary);
}

.form-label {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.btn-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-weight: 600;
    padding: 10px 25px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-gradient:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px var(--shadow-lg);
}

.table-container {
    background: var(--bg-primary);
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 0 20px var(--shadow-sm);
    border: 1px solid var(--border-color);
}

/* Animação para o ícone de collapse */
.filter-header .feather-chevron-down {
    transition: transform 0.3s ease;
}

.filter-header.collapsed .feather-chevron-down {
    transform: rotate(-90deg);
}

/* ========================================
   CARDS DE ESTATÍSTICAS
   ======================================== */

.stats-card {
    background: var(--bg-primary);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px var(--shadow-sm);
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px var(--shadow-md);
}

/* Cards específicos por tipo */
.stats-card-pendente {
    background: var(--stats-pendente-bg);
    border-color: var(--stats-pendente-color);
}

.stats-card-pendente .stats-number {
    color: var(--stats-pendente-text);
}

.stats-card-pendente i {
    color: var(--stats-pendente-color);
}

.stats-card-anotada {
    background: var(--stats-anotada-bg);
    border-color: var(--stats-anotada-color);
}

.stats-card-anotada .stats-number {
    color: var(--stats-anotada-text);
}

.stats-card-anotada i {
    color: var(--stats-anotada-color);
}

.stats-card-recusada {
    background: var(--stats-recusada-bg);
    border-color: var(--stats-recusada-color);
}

.stats-card-recusada .stats-number {
    color: var(--stats-recusada-text);
}

.stats-card-recusada i {
    color: var(--stats-recusada-color);
}

.stats-card-total {
    background: var(--stats-total-bg);
    border-color: var(--stats-total-color);
}

.stats-card-total .stats-number {
    color: var(--stats-total-text);
}

.stats-card-total i {
    color: var(--stats-total-color);
}

.stats-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 0;
}

.stats-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

/* Modo Dark - ajustes adicionais para cards */
[data-bs-theme="dark"] .stats-card,
.dark-mode .stats-card {
    border-width: 2px;
}

[data-bs-theme="dark"] .stats-label,
.dark-mode .stats-label {
    color: var(--text-muted);
}

/* ========================================
   STATUS BADGES
   ======================================== */

.badge-status-pendente {
    background-color: #ffc107;
    color: #000;
}

.badge-status-anotada {
    background-color: #28a745;
    color: #fff;
}

.badge-status-recusada {
    background-color: #dc3545;
    color: #fff;
}

/* ========================================
   BADGES DE TIPOS - LIGHT MODE
   ======================================== */

.badge-tipo-casamento {
    background-color: #FFE4E1;
    color: #8B3A3A;
    border: 1px solid #FFC0CB;
    font-weight: 600;
}

.badge-tipo-nascimento {
    background-color: #E6F9E6;
    color: #2E7D2E;
    border: 1px solid #B8E6B8;
    font-weight: 600;
}

.badge-tipo-obito {
    background-color: #E8E3F0;
    color: #4A4555;
    border: 1px solid #C9BFD8;
    font-weight: 600;
}

.badge-tipo-alteracao {
    background-color: #E3F2FD;
    color: #1565C0;
    border: 1px solid #BBDEFB;
    font-weight: 600;
}

.badge-tipo-interdicao {
    background-color: #FFEEDD;
    color: #CC6600;
    border: 1px solid #FFD4AA;
    font-weight: 600;
}

.badge-tipo-curatela {
    background-color: #FFF9E6;
    color: #8B6914;
    border: 1px solid #FFE4B5;
    font-weight: 600;
}

.badge-tipo-emancipacao {
    background-color: #E0F7FA;
    color: #00695C;
    border: 1px solid #B2EBF2;
    font-weight: 600;
}

.badge-tipo-adocao {
    background-color: #F3E5F5;
    color: #6A1B9A;
    border: 1px solid #E1BEE7;
    font-weight: 600;
}

.badge-tipo-divorcio {
    background-color: #FFE5E5;
    color: #B71C1C;
    border: 1px solid #FFCDD2;
    font-weight: 600;
}

.badge-tipo-retificacao {
    background-color: #F5F0E6;
    color: #5D4037;
    border: 1px solid #E0D4C1;
    font-weight: 600;
}

.badge-tipo-conversao {
    background-color: #FCE4EC;
    color: #880E4F;
    border: 1px solid #F8BBD0;
    font-weight: 600;
}

.badge-tipo-outros,
.badge-tipo-default {
    background-color: #ECEFF1;
    color: #455A64;
    border: 1px solid #CFD8DC;
    font-weight: 600;
}

/* ========================================
   BADGES DE TIPOS - DARK MODE
   ======================================== */

[data-bs-theme="dark"] .badge-tipo-casamento,
.dark-mode .badge-tipo-casamento {
    background-color: #5C2E2E;
    color: #FFB3BA;
    border: 1px solid #7A3F45;
}

[data-bs-theme="dark"] .badge-tipo-nascimento,
.dark-mode .badge-tipo-nascimento {
    background-color: #1B4D1B;
    color: #90EE90;
    border: 1px solid #2D6B2D;
}

[data-bs-theme="dark"] .badge-tipo-obito,
.dark-mode .badge-tipo-obito {
    background-color: #3E3B47;
    color: #C8C3D1;
    border: 1px solid #534E5E;
}

[data-bs-theme="dark"] .badge-tipo-alteracao,
.dark-mode .badge-tipo-alteracao {
    background-color: #1E3A5F;
    color: #90CAF9;
    border: 1px solid #2C5282;
}

[data-bs-theme="dark"] .badge-tipo-interdicao,
.dark-mode .badge-tipo-interdicao {
    background-color: #663300;
    color: #FFB366;
    border: 1px solid #804000;
}

[data-bs-theme="dark"] .badge-tipo-curatela,
.dark-mode .badge-tipo-curatela {
    background-color: #4D4100;
    color: #FFD966;
    border: 1px solid #665500;
}

[data-bs-theme="dark"] .badge-tipo-emancipacao,
.dark-mode .badge-tipo-emancipacao {
    background-color: #004D40;
    color: #80CBC4;
    border: 1px solid #00695C;
}

[data-bs-theme="dark"] .badge-tipo-adocao,
.dark-mode .badge-tipo-adocao {
    background-color: #4A148C;
    color: #CE93D8;
    border: 1px solid #6A1B9A;
}

[data-bs-theme="dark"] .badge-tipo-divorcio,
.dark-mode .badge-tipo-divorcio {
    background-color: #5D1F1F;
    color: #FFCDD2;
    border: 1px solid #7A2828;
}

[data-bs-theme="dark"] .badge-tipo-retificacao,
.dark-mode .badge-tipo-retificacao {
    background-color: #3E2723;
    color: #D7CCC8;
    border: 1px solid #5D4037;
}

[data-bs-theme="dark"] .badge-tipo-conversao,
.dark-mode .badge-tipo-conversao {
    background-color: #4A0E2F;
    color: #F8BBD0;
    border: 1px solid #6D1946;
}

[data-bs-theme="dark"] .badge-tipo-outros,
.dark-mode .badge-tipo-outros,
[data-bs-theme="dark"] .badge-tipo-default,
.dark-mode .badge-tipo-default {
    background-color: #37474F;
    color: #B0BEC5;
    border: 1px solid #546E7A;
}

/* ========================================
   HOVER EFFECTS PARA BADGES - LIGHT MODE
   ======================================== */

.badge-tipo-casamento:hover {
    background-color: #FFC0CB;
    color: #6B2C2C;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255, 192, 203, 0.3);
}

.badge-tipo-nascimento:hover {
    background-color: #C8E6C9;
    color: #1B5E20;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(200, 230, 201, 0.3);
}

.badge-tipo-obito:hover {
    background-color: #D1C4E9;
    color: #311B92;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(209, 196, 233, 0.3);
}

.badge-tipo-alteracao:hover {
    background-color: #BBDEFB;
    color: #0D47A1;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(187, 222, 251, 0.3);
}

.badge-tipo-interdicao:hover {
    background-color: #FFD4AA;
    color: #A65200;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255, 212, 170, 0.3);
}

.badge-tipo-curatela:hover {
    background-color: #FFE082;
    color: #6B5210;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255, 224, 130, 0.3);
}

/* ========================================
   HOVER EFFECTS PARA BADGES - DARK MODE
   ======================================== */

[data-bs-theme="dark"] .badge-tipo-casamento:hover,
.dark-mode .badge-tipo-casamento:hover {
    background-color: #7A3F45;
    color: #FFCDD2;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
}

[data-bs-theme="dark"] .badge-tipo-nascimento:hover,
.dark-mode .badge-tipo-nascimento:hover {
    background-color: #2D6B2D;
    color: #B9F6CA;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
}

[data-bs-theme="dark"] .badge-tipo-obito:hover,
.dark-mode .badge-tipo-obito:hover {
    background-color: #534E5E;
    color: #E1BEE7;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
}

/* ========================================
   ESTILOS GERAIS PARA BADGES DE TIPO
   ======================================== */

[class*="badge-tipo-"] {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.375rem;
    transition: all 0.3s ease;
    cursor: default;
}

/* ========================================
   ÍCONES PARA CADA TIPO
   ======================================== */

.badge-tipo-casamento::before {
    content: "💑";
    margin-right: 4px;
}

.badge-tipo-nascimento::before {
    content: "👶";
    margin-right: 4px;
}

.badge-tipo-obito::before {
    content: "🕊️";
    margin-right: 4px;
}

.badge-tipo-alteracao::before {
    content: "📝";
    margin-right: 4px;
}

.badge-tipo-interdicao::before {
    content: "⚖️";
    margin-right: 4px;
}

.badge-tipo-curatela::before {
    content: "🛡️";
    margin-right: 4px;
}

.badge-tipo-emancipacao::before {
    content: "🎓";
    margin-right: 4px;
}

.badge-tipo-adocao::before {
    content: "🏠";
    margin-right: 4px;
}

.badge-tipo-divorcio::before {
    content: "💔";
    margin-right: 4px;
}

.badge-tipo-retificacao::before {
    content: "✏️";
    margin-right: 4px;
}

.badge-tipo-icon {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.badge-tipo-icon svg {
    width: 14px;
    height: 14px;
}

/* ========================================
   MODAL E COMPONENTES RELACIONADOS
   ======================================== */

.bg-gradient-primary {  
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);  
}  

[data-bs-theme="dark"] .bg-gradient-primary,
.dark-mode .bg-gradient-primary {
    background: linear-gradient(135deg, #0a58ca 0%, #084298 100%);
}

.info-card {  
    transition: all 0.3s ease;  
}  

.info-card:hover {  
    transform: translateY(-2px);  
}  

.fs-7 {  
    font-size: 0.875rem;  
}  

/* ========================================
   TABS PERSONALIZADAS
   ======================================== */

.nav-tabs .nav-link {  
    color: var(--text-secondary);  
    font-weight: 500;  
    border: none;  
    border-bottom: 2px solid transparent;  
    padding: 0.75rem 1.25rem;  
    transition: all 0.3s ease;  
}  

.nav-tabs .nav-link:hover {  
    color: #0d6efd;  
    border-bottom-color: var(--border-color);  
}  

.nav-tabs .nav-link.active {  
    color: #0d6efd;  
    background-color: transparent;  
    border: none;  
    border-bottom: 2px solid #0d6efd;  
}  

[data-bs-theme="dark"] .nav-tabs .nav-link.active,
.dark-mode .nav-tabs .nav-link.active {
    color: #6ea8fe;
    border-bottom-color: #6ea8fe;
}

/* ========================================
   CONTEÚDO DAS COMUNICAÇÕES
   ======================================== */

.comunicacao-content,  
.anotacao-content {  
    max-height: 400px;  
    overflow-y: auto;  
    line-height: 1.6;  
    font-size: 0.95rem;  
    background-color: var(--bg-secondary);  
    border: 1px solid var(--border-color);  
    color: var(--text-primary);
}  

.comunicacao-content {  
    font-family: 'Roboto Mono', monospace;  
}  

.anotacao-content {  
    font-family: 'Arial', sans-serif;  
    background-color: var(--bg-primary);  
}  

/* ========================================
   TEXTO INTEGRAL FORMATADO
   ======================================== */

.texto-integral-formatado {  
    font-size: 0.95rem;  
    line-height: 1.6;  
    color: var(--text-primary);
}  

.texto-integral-formatado h6 {  
    font-size: 1rem;  
    font-weight: 600;  
    margin-bottom: 0.5rem;  
    color: var(--text-primary);
}  

.texto-integral-formatado .alert {  
    font-size: 0.9rem;  
    border-left: 3px solid #0d6efd;  
}  

.texto-integral-formatado code {  
    background-color: var(--bg-tertiary);  
    padding: 2px 4px;  
    border-radius: 3px;  
    color: #d63384;  
    font-size: 0.875rem;  
}  

[data-bs-theme="dark"] .texto-integral-formatado code,
.dark-mode .texto-integral-formatado code {
    color: #f783ac;
    background-color: var(--bg-tertiary);
}

.texto-integral-formatado strong {  
    color: var(--text-primary);  
}  

.texto-integral-formatado .text-decoration-underline {  
    text-decoration-style: dotted !important;  
    text-decoration-color: var(--text-secondary) !important;  
}  

.texto-integral-formatado .fst-italic {  
    color: var(--text-secondary);  
    font-size: 0.9rem;  
}  

.texto-integral-formatado small {  
    display: block;  
    margin-top: 0.25rem;  
    color: var(--text-muted);
}  

/* ========================================
   TEXTO ANOTAÇÃO FORMATADO
   ======================================== */

.texto-anotacao-formatado {  
    font-size: 0.95rem;  
    line-height: 1.6;  
    color: var(--text-primary);  
}  

.texto-anotacao-formatado .texto-intro {  
    font-weight: 600;  
    color: var(--text-primary);  
}  

.texto-anotacao-formatado .data-destaque {  
    font-weight: bold;  
    color: #0d6efd;  
    background-color: #e7f1ff;  
    padding: 1px 3px;  
    border-radius: 3px;  
}  

[data-bs-theme="dark"] .texto-anotacao-formatado .data-destaque,
.dark-mode .texto-anotacao-formatado .data-destaque {
    color: #6ea8fe;
    background-color: rgba(110, 168, 254, 0.2);
}

.texto-anotacao-formatado .livro-destaque,  
.texto-anotacao-formatado .folha-destaque,  
.texto-anotacao-formatado .termo-destaque {  
    text-decoration: underline;  
    text-decoration-style: dotted;  
    text-decoration-color: var(--text-secondary);  
    font-weight: 500;  
}  

.texto-anotacao-formatado .nome-destaque {  
    font-weight: bold;  
    color: var(--text-primary);  
    background-color: #fff3cd;  
    padding: 1px 3px;  
    border-radius: 3px;  
}  

[data-bs-theme="dark"] .texto-anotacao-formatado .nome-destaque,
.dark-mode .texto-anotacao-formatado .nome-destaque {
    background-color: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.texto-anotacao-formatado .codigo-destaque {  
    font-family: 'Courier New', monospace;  
    background-color: var(--bg-tertiary);  
    color: #d63384;  
    padding: 2px 4px;  
    border-radius: 3px;  
    font-weight: bold;  
}  

[data-bs-theme="dark"] .texto-anotacao-formatado .codigo-destaque,
.dark-mode .texto-anotacao-formatado .codigo-destaque {
    color: #f783ac;
}

.texto-anotacao-formatado .cartorio-destaque {  
    font-weight: 600;  
    color: #198754;  
}  

[data-bs-theme="dark"] .texto-anotacao-formatado .cartorio-destaque,
.dark-mode .texto-anotacao-formatado .cartorio-destaque {
    color: #75b798;
}

.texto-anotacao-formatado .processo-destaque {  
    font-family: 'Courier New', monospace;  
    background-color: var(--bg-tertiary);  
    color: var(--text-primary);  
    padding: 1px 3px;  
    border-radius: 3px;  
    font-size: 0.9em;  
}  

.texto-anotacao-formatado .texto-final {  
    font-style: italic;  
    color: var(--text-secondary);  
}  

.texto-anotacao-formatado .local-data {  
    font-weight: 600;  
    color: var(--text-secondary);  
}  

.texto-anotacao-formatado span[class$="-destaque"] {  
    transition: all 0.2s ease;  
}  

.texto-anotacao-formatado span[class$="-destaque"]:hover {  
    transform: scale(1.05);  
    box-shadow: 0 0 5px var(--shadow-sm);  
}  

/* ========================================
   ETIQUETA PREVIEW
   ======================================== */

.etiqueta-preview {  
    border: 2px dashed var(--border-color);  
    background-color: var(--bg-primary);  
    min-height: 200px;  
    font-size: 10pt;  
    overflow: hidden;  
    box-shadow: 0 2px 4px var(--shadow-sm);  
    display: flex;  
    align-items: center;  
    justify-content: center;  
    color: var(--text-primary);
}  

.etiqueta-preview.size-9x3\.5 {  
    width: 340px;
    height: 132px;
}  

.etiqueta-preview.size-9x4 {  
    width: 340px;
    height: 151px;
}  

.etiqueta-preview.size-10x4 {  
    width: 377px;
    height: 151px;
}  

.etiqueta-preview.size-10x5 {  
    width: 377px;
    height: 189px;
}  

.etiqueta-preview .etiqueta-conteudo {  
    text-align: justify;  
    word-break: break-word;  
    width: 100%;  
    height: 100%;  
    overflow: hidden;  
}  

.etiqueta-conteudo strong {  
    font-weight: bold;  
    color: var(--text-primary);  
}  

.etiqueta-conteudo u {  
    text-decoration: underline;  
    text-decoration-style: dotted;  
}  

/* ========================================
   DROPZONE
   ======================================== */

.dropzone-container {  
    margin-bottom: 20px;  
}  

.dropzone-area {  
    border: 2px dashed var(--border-color);  
    border-radius: 8px;  
    padding: 40px 20px;  
    text-align: center;  
    cursor: pointer;  
    transition: border-color 0.3s, background-color 0.3s;  
    position: relative;  
    overflow: hidden;  
    background-color: var(--bg-primary);
}  

.dropzone-area:hover {  
    border-color: var(--text-secondary);  
    background-color: var(--bg-secondary);  
}  

.dropzone-active {  
    border-color: #0d6efd;  
    background-color: rgba(13, 110, 253, 0.05);  
}  

[data-bs-theme="dark"] .dropzone-active,
.dark-mode .dropzone-active {
    background-color: rgba(13, 110, 253, 0.1);
}

.dz-message {  
    color: var(--text-secondary);  
}  

.dz-message i {  
    margin-bottom: 15px;  
}  

.dz-message p {  
    font-size: 18px;  
    margin-bottom: 5px;  
    color: var(--text-primary);
}  

.dz-message .note {  
    font-size: 12px;  
    color: var(--text-muted);  
}  

.file-input {  
    position: absolute;  
    top: 0;  
    left: 0;  
    width: 100%;  
    height: 100%;  
    opacity: 0;  
    cursor: pointer;  
}  

#selected-file {  
    background-color: var(--bg-secondary);  
    padding: 10px;  
    border-radius: 4px;  
    max-width: 100%;  
    color: var(--text-primary);
}  

#file-name {  
    max-width: 70%;  
    display: inline-block;  
    overflow: hidden;  
}  

/* ========================================
   BOTÕES DE STATUS
   ======================================== */

.btn-status-pendente {  
    background-color: #f8f9fa;  
    border-color: #dee2e6;  
    color: #495057;  
}  

[data-bs-theme="dark"] .btn-status-pendente,
.dark-mode .btn-status-pendente {
    background-color: #495057;
    border-color: #6c757d;
    color: #f8f9fa;
}

.btn-status-anotada {  
    background-color: #d1e7dd;  
    border-color: #badbcc;  
    color: #0f5132;  
}  

[data-bs-theme="dark"] .btn-status-anotada,
.dark-mode .btn-status-anotada {
    background-color: #0f5132;
    border-color: #146c43;
    color: #d1e7dd;
}

.btn-status-recusada {  
    background-color: #f8d7da;  
    border-color: #f5c2c7;  
    color: #842029;  
}  

[data-bs-theme="dark"] .btn-status-recusada,
.dark-mode .btn-status-recusada {
    background-color: #842029;
    border-color: #b02a37;
    color: #f8d7da;
}

.btn-status-excluido {  
    background-color: #6c757d;  
    border-color: #6c757d;  
    color: #fff;  
}  

.status-btn {  
    transition: all 0.3s ease;  
}  

/* ========================================
   BADGE DE STATUS PARA MODAL
   ======================================== */

#detalhe-status.badge {  
    font-size: 0.875rem;  
    padding: 0.35em 0.65em;  
}  

#detalhe-status.badge.badge-pendente {  
    background-color: #6c757d;  
    color: white;  
}  

#detalhe-status.badge.badge-anotada {  
    background-color: #198754;  
    color: white;  
}  

#detalhe-status.badge.badge-recusada {  
    background-color: #dc3545;  
    color: white;  
}  

/* ========================================
   DROPDOWN ITEMS
   ======================================== */

.dropdown-item:hover {  
    background-color: var(--bg-secondary);  
}  

.dropdown-item[data-status="anotada"]:hover {  
    background-color: #d1e7dd;  
}  

[data-bs-theme="dark"] .dropdown-item[data-status="anotada"]:hover,
.dark-mode .dropdown-item[data-status="anotada"]:hover {
    background-color: rgba(25, 135, 84, 0.2);
}

.dropdown-item[data-status="recusada"]:hover {  
    background-color: #f8d7da;  
}  

[data-bs-theme="dark"] .dropdown-item[data-status="recusada"]:hover,
.dark-mode .dropdown-item[data-status="recusada"]:hover {
    background-color: rgba(220, 53, 69, 0.2);
}

/* ========================================
   SCROLLBAR PERSONALIZADA
   ======================================== */

.comunicacao-content::-webkit-scrollbar,  
.anotacao-content::-webkit-scrollbar {  
    width: 8px;  
}  

.comunicacao-content::-webkit-scrollbar-track,  
.anotacao-content::-webkit-scrollbar-track {  
    background: var(--bg-secondary);  
    border-radius: 4px;  
}  

.comunicacao-content::-webkit-scrollbar-thumb,  
.anotacao-content::-webkit-scrollbar-thumb {  
    background: var(--text-muted);  
    border-radius: 4px;  
}  

.comunicacao-content::-webkit-scrollbar-thumb:hover,  
.anotacao-content::-webkit-scrollbar-thumb:hover {  
    background: var(--text-secondary);  
}  

/* ========================================
   AJUSTES PARA TABELAS EM DARK MODE
   ======================================== */

[data-bs-theme="dark"] .table,
.dark-mode .table {
    color: var(--text-primary);
}

[data-bs-theme="dark"] .table-hover tbody tr:hover,
.dark-mode .table-hover tbody tr:hover {
    background-color: var(--bg-tertiary);
    color: var(--text-primary);
}

/* ========================================
   RESPONSIVIDADE
   ======================================== */

@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 15px;
    }
    
    .filter-body {
        padding: 15px;
    }
    
    .form-control, .form-select {
        font-size: 16px;
    }
    
    [class*="badge-tipo-"] {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
}

/* ========================================
   ESTILOS PARA IMPRESSÃO
   ======================================== */

@media print {  
    body * {  
        visibility: hidden;  
    }  
    
    #etiqueta-print, #etiqueta-print * {  
        visibility: visible;  
    }  
    
    #etiqueta-print {  
        position: absolute;  
        left: 0;  
        top: 0;  
        width: 100%;  
    }  
    
    @page {  
        size: 10cm 5cm;  
        margin: 0;  
    }  
}  

/* ========================================
   TRANSIÇÕES SUAVES ENTRE TEMAS
   ======================================== */

* {
    transition: background-color 0.1s ease, color 0.1s ease, border-color 0.1s ease;
}
</style>