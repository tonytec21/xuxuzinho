
<!-- Adicionar estilos personalizados no cabeçalho -->  
<style>  
/* Estilos gerais e variáveis de cores */  
:root {  
    --primary-color: #4e73df;  
    --primary-hover: #2e59d9;  
    --success-color: #1cc88a;  
    --warning-color: #f6c23e;  
    --danger-color: #e74a3b;  
    --info-color: #36b9cc;  
    --gray-light: #f8f9fc;  
    --gray-medium: #eaecf4;  
    --gray-dark: #5a5c69;  
    --border-radius: 0.35rem;  
    --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);  
    --shadow-md: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);  
    --transition: all 0.2s ease-in-out;  
}  

/* Melhorias gerais */  
body {  
    background-color: #f8f9fc;  
}  

.card {  
    border: none;  
    border-radius: var(--border-radius);  
    box-shadow: var(--shadow-sm);  
    transition: var(--transition);  
    overflow: hidden;  
}  

.card:hover {  
    box-shadow: var(--shadow-md);  
}  

.card-header {  
    border-bottom: 1px solid var(--gray-medium);  
    padding: 1rem 1.25rem;  
}  

.card-header h5 {  
    font-weight: 600;  
    color: var(--gray-dark);  
}  

.form-label {  
    font-weight: 500;  
    margin-bottom: 0.4rem;  
    color: var(--gray-dark);  
}  

.form-control, .form-select {  
    border-radius: var(--border-radius);  
    padding: 0.6rem 0.75rem;  
    border: 1px solid #d1d3e2;  
    transition: var(--transition);  
}  

.form-control:focus, .form-select:focus {  
    border-color: var(--primary-color);  
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);  
}  

.bg-light {  
    background-color: var(--gray-light) !important;  
}  

/* Botões aprimorados */  
.btn {  
    border-radius: var(--border-radius);  
    padding: 0.5rem 1rem;  
    font-weight: 500;  
    display: inline-flex;  
    align-items: center;  
    justify-content: center;  
    transition: var(--transition);  
}  

.btn-primary {  
    background-color: var(--primary-color);  
    border-color: var(--primary-color);  
}  

.btn-primary:hover {  
    background-color: var(--primary-hover);  
    border-color: var(--primary-hover);  
}  

.btn-sm {  
    padding: 0.25rem 0.5rem;  
    font-size: 0.82rem;  
}  

.btn i, .btn svg {  
    margin-right: 0.4rem;  
    width: 18px !important;  
    height: 18px !important;  
}  

.btn-sm i, .btn-sm svg {  
    width: 16px !important;  
    height: 16px !important;  
    margin-right: 0.2rem;  
}  

/* Badges aprimorados */  
.badge {  
    font-weight: 600;  
    padding: 0.5rem 0.75rem;  
    border-radius: 0.40rem;  
    display: inline-flex;  
    align-items: center;  
    font-size: 0.75rem;  
}  

.badge i, .badge svg {  
    margin-right: 0.3rem;  
    width: 13px !important;  
    height: 13px !important;  
}  

/* Para Nascimento - tom azul-pastel suave (representa novo começo) */  
.badge-nascimento {  
    background-color: #B6E3E9; /* Azul suave */  
    color: #2C7D8C;  
    border: 1px solid #A5D1D7;  
}  

/* Para Casamento - tom rosa-romântico (representa união) */  
.badge-casamento {  
    background-color: #F8D0E3; /* Rosa suave */  
    color: #AF4D7E;  
    border: 1px solid #EBBFD2;  
}

/* Upload area */  
.upload-area {  
    border: 2px dashed #d1d3e2;  
    border-radius: var(--border-radius);  
    padding: 2rem;  
    text-align: center;  
    transition: var(--transition);  
    background-color: var(--gray-light);  
}  

.upload-area.highlight {  
    border-color: var(--primary-color);  
    background-color: rgba(78, 115, 223, 0.05);  
}  

.dz-message h5 {  
    margin-top: 1rem;  
    color: var(--gray-dark);  
}  

.browse-btn {  
    margin-top: 1rem;  
    padding: 0.5rem 1.5rem;  
}  

/* Tabelas responsivas */  
.table-responsive {  
    overflow-x: auto;  
    -webkit-overflow-scrolling: touch;  
}  

.table {  
    width: 100%;  
    margin-bottom: 1rem;  
    color: #212529;  
    vertical-align: middle;  
    border-color: #e3e6f0;  
}  

.table th {  
    font-weight: 600;  
    color: var(--gray-dark);  
    background-color: var(--gray-light);  
    white-space: nowrap;  
    padding: 0.75rem 1rem;  
}  

.table td {  
    padding: 0.75rem 1rem;  
    vertical-align: middle;  
}  

.table-bordered {  
    border: 1px solid #e3e6f0;  
}  

.table-striped tbody tr:nth-of-type(odd) {  
    background-color: rgba(0,0,0,0.02);  
}  

/* Lista de arquivos */  
.file-preview-list {  
    background-color: var(--gray-light);  
    border-radius: var(--border-radius);  
    padding: 1rem;  
    max-height: 200px;  
    overflow-y: auto;  
}  

.file-preview-list div {  
    padding: 0.5rem;  
    margin-bottom: 0.5rem;  
    background-color: white;  
    border-radius: var(--border-radius);  
    box-shadow: var(--shadow-sm);  
}  

/* Modal para seleção de cidade */  
.city-search-results {  
    max-height: 300px;  
    overflow-y: auto;  
}  

.city-item {  
    padding: 10px;  
    border-bottom: 1px solid var(--gray-medium);  
    cursor: pointer;  
    transition: var(--transition);  
}  

.city-item:hover {  
    background-color: var(--gray-light);  
}  

.city-item:last-child {  
    border-bottom: none;  
}  

.search-highlight {  
    background-color: rgba(255, 204, 0, 0.2);  
    font-weight: bold;  
}  

/* Animações para elementos dinâmicos */  
.section-fade {  
    transition: all 0.3s ease-in-out;  
}  

.section-fade.hidden {  
    opacity: 0;  
    max-height: 0;  
    overflow: hidden;  
    margin: 0;  
    padding: 0;  
}  

.section-fade.visible {  
    opacity: 1;  
    max-height: 1000px;  
}  

/* Responsividade */  
@media (max-width: 768px) {  
    .btn-group-responsive {  
        display: flex;  
        flex-direction: column;  
        gap: 0.5rem;  
    }  
    
    .table-responsive-sm {  
        display: block;  
        width: 100%;  
        overflow-x: auto;  
        -webkit-overflow-scrolling: touch;  
    }  
    
    .mobile-full {  
        width: 100% !important;  
    }  
}  

.rounded-circle {
    background: #fff;
}

/* Animações */  
@keyframes fadeIn {  
    from { opacity: 0; }  
    to { opacity: 1; }  
}  

.animate-fadeIn {  
    animation: fadeIn 0.5s ease-in-out;  
}  

/* melhora leve */
.btn{box-shadow:var(--shadow-sm)}
.btn:active{transform:scale(.97)}
.form-control,.form-select{font-size:.95rem}

</style>  