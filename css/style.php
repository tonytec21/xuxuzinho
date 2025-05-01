<style>
/* style.css - CSS atualizado para o sistema Xuxuzinho */  

:root {  
    --primary-color: #0d6efd;  
    --secondary-color: #6c757d;  
    --dark-bg: #212529;  
    --light-bg: #f8f9fa;  
    --success-color: #198754;  
    --info-color: #0dcaf0;  
    --warning-color: #ffc107;  
    --danger-color: #dc3545;  
    --sidebar-width: 250px;  
    --header-height: 60px;  
    --sidebar-dark: #1a1d20;  
    --text-light: #f8f9fa;  
    --text-dark: #212529;  
    --border-color: #343a40;  
}  

/* Base styles */  
body {  
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;  
    margin: 0;  
    padding: 0;  
    background-color: #f8f9fa;  
    color: #212529;  
    transition: background-color 0.3s ease, color 0.3s ease;  
}  

[data-bs-theme="dark"] body {  
    background-color: #2c3034;  
    color: #e9ecef;  
}  

/* Wrapper */  
.wrapper {  
    display: flex;  
    min-height: 100vh;  
    width: 100%;  
    position: relative;  
}  

/* Sidebar */  
#sidebar {  
    width: var(--sidebar-width);  
    position: fixed;  
    top: 0;  
    left: 0;  
    height: 100vh;  
    background-color: #212529;  
    color: white;  
    z-index: 1000;  
    overflow-y: auto;  
    transition: all 0.3s;  
}  

#sidebar.collapsed {  
    margin-left: -250px;  
}  

#sidebar .sidebar-header {  
    padding: 15px;  
    background-color: #1a1d20;  
    text-align: center;  
}  

#sidebar .sidebar-header img {  
    max-width: 180px;  
    height: auto;  
}  

#sidebar ul.components {  
    padding: 20px 0;  
    list-style: none;  
}  

#sidebar ul li {  
    margin-bottom: 2px;  
}  

#sidebar ul li a {  
    padding: 10px 15px;  
    display: flex;  
    align-items: center;  
    color: #adb5bd;  
    text-decoration: none;  
    transition: all 0.3s;  
    border-left: 3px solid transparent;  
}  

#sidebar ul li a:hover {  
    color: #ffffff;  
    background: rgba(255, 255, 255, 0.05);  
    border-left: 3px solid var(--primary-color);  
}  

#sidebar ul li.active > a {  
    color: #ffffff;  
    background: rgba(255, 255, 255, 0.05);  
    border-left: 3px solid var(--primary-color);  
}  

#sidebar ul li a svg {  
    margin-right: 10px;  
    width: 20px;  
    height: 20px;  
    color: inherit;  
}  

/* Sidebar Footer */  
.sidebar-footer {  
    padding: 15px;  
    border-top: 1px solid rgba(255, 255, 255, 0.1);  
    position: absolute;  
    bottom: 0;  
    width: 100%;  
}  

/* Dark Mode Toggle */  
.theme-switch-wrapper {  
    display: flex;  
    align-items: center;  
    margin-top: 20px;  
    margin-bottom: 15px;  
    padding: 0 15px;  
}  

.theme-switch {  
    display: inline-block;  
    height: 24px;  
    position: relative;  
    width: 48px;  
    margin-right: 10px;  
}  

.theme-switch input {  
    display: none;  
}  

.slider {  
    background-color: #484e53;  
    bottom: 0;  
    cursor: pointer;  
    left: 0;  
    position: absolute;  
    right: 0;  
    top: 0;  
    transition: .4s;  
}  

.slider:before {  
    background-color: #fff;  
    bottom: 4px;  
    content: "";  
    height: 16px;  
    left: 4px;  
    position: absolute;  
    transition: .4s;  
    width: 16px;  
}  

input:checked + .slider {  
    background-color: var(--primary-color);  
}  

input:checked + .slider:before {  
    transform: translateX(24px);  
}  

.slider.round {  
    border-radius: 34px;  
}  

.slider.round:before {  
    border-radius: 50%;  
}  

/* Content Area */  
#content {  
    width: calc(100% - var(--sidebar-width));  
    margin-left: var(--sidebar-width);  
    transition: all 0.3s;  
    position: relative;  
    min-height: 100vh;  
}  

#content.full-width {  
    width: 100%;  
    margin-left: 0;  
}  

/* Navbar */  
.navbar {  
    padding: 15px 20px;  
    background-color: #fff;  
    border-bottom: 1px solid #dee2e6;  
    display: flex;  
    justify-content: space-between;  
    align-items: center;  
}  

[data-bs-theme="dark"] .navbar {  
    background-color: #343a40;  
    border-bottom: 1px solid #495057;  
}  

.navbar-btn {  
    background: transparent;  
    border: none;  
    cursor: pointer;  
    padding: 5px;  
    color: var(--secondary-color);  
}  

[data-bs-theme="dark"] .navbar-btn {  
    color: #adb5bd;  
}  

/* User Info */  
.user-info {  
    display: flex;  
    align-items: center;  
    position: absolute;  
    right: 20px;  
    top: 10px;  
}  

.user-info img.avatar {  
    width: 32px;  
    height: 32px;  
    border-radius: 50%;  
    margin-right: 10px;  
}  

.user-info .user-name {  
    font-weight: bold;  
    margin: 0;  
    padding: 0;  
    font-size: 14px;  
}  

.user-info .user-role {  
    margin: 0;  
    padding: 0;  
    font-size: 12px;  
    color: var(--secondary-color);  
}  

/* Main Container */  
.main-container {  
    padding: 20px;  
}  

/* Cards and Panels */  
.card {  
    border: 1px solid rgba(0,0,0,.125);  
    border-radius: 0.25rem;  
    margin-bottom: 20px;  
}  

[data-bs-theme="dark"] .card {  
    background-color: #343a40;  
    border-color: #495057;  
}  

.card-header {  
    padding: 0.75rem 1.25rem;  
    margin-bottom: 0;  
    background-color: rgba(0,0,0,.03);  
    border-bottom: 1px solid rgba(0,0,0,.125);  
}  

[data-bs-theme="dark"] .card-header {  
    background-color: #2c3034!important;  
    border-bottom-color: #495057;  
}  

[data-bs-theme="dark"] .bg-light {
    background-color: #343a40!important;
    border-color: #495057!important;
    color: #f8f9fa;
}

[data-bs-theme="dark"] .table {
    --bs-table-bg: #343a40;
}

.card-body {  
    padding: 1.25rem;  
}  

/* Stats Circles */  
.stat-circle {  
    display: flex;  
    flex-direction: column;  
    align-items: center;  
    justify-content: center;  
    width: 100px;  
    height: 100px;  
    border-radius: 50%;  
    color: white;  
    margin: 0 auto 15px;  
}  

.stat-circle.blue {  
    background-color: var(--primary-color);  
}  

.stat-circle.green {  
    background-color: var(--success-color);  
}  

.stat-value {  
    font-size: 24px;  
    font-weight: bold;  
    line-height: 1;  
}  

.stat-label {  
    font-size: 12px;  
    margin-top: 5px;  
}  

/* Tables */  
.table {  
    width: 100%;  
    margin-bottom: 1rem;  
    color: #212529;  
    border-collapse: collapse;  
}  

[data-bs-theme="dark"] .table {  
    color: #e9ecef;  
}  

.table th,  
.table td {  
    padding: 0.75rem;  
    vertical-align: middle;  
    border-top: 1px solid #dee2e6;  
}  

[data-bs-theme="dark"] .table th,  
[data-bs-theme="dark"] .table td {  
    border-top-color: #495057;  
}  

.table thead th {  
    vertical-align: bottom;  
    border-bottom: 2px solid #dee2e6;  
}  

[data-bs-theme="dark"] .table thead th {  
    border-bottom-color: #495057;  
}  

.table-striped tbody tr:nth-of-type(odd) {  
    background-color: rgba(0,0,0,.05);  
}  

[data-bs-theme="dark"] .table-striped tbody tr:nth-of-type(odd) {  
    background-color: rgba(255,255,255,.05);  
}  

/* Badges */  
.badge {  
    display: inline-block;  
    padding: 0.25em 0.4em;  
    font-size: 75%;  
    font-weight: 700;  
    line-height: 1;  
    text-align: center;  
    white-space: nowrap;  
    vertical-align: baseline;  
    border-radius: 0.25rem;  
}  

.badge-primary {  
    color: #fff;  
    background-color: var(--primary-color);  
}  

.badge-success {  
    color: #fff;  
    background-color: var(--success-color);  
}  

.badge-info {  
    color: #fff;  
    background-color: var(--info-color);  
}  

.badge-warning {  
    color: #212529;  
    background-color: var(--warning-color);  
}  

.badge-danger {  
    color: #fff;  
    background-color: var(--danger-color);  
}  

/* Buttons */  
.btn {  
    display: inline-block;  
    font-weight: 400;  
    text-align: center;  
    white-space: nowrap;  
    vertical-align: middle;  
    user-select: none;  
    border: 1px solid transparent;  
    padding: 0.375rem 0.75rem;  
    font-size: 1rem;  
    line-height: 1.5;  
    border-radius: 0.25rem;  
    transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;  
}  

.btn-primary {  
    color: #fff;  
    background-color: var(--primary-color);  
    border-color: var(--primary-color);  
}  

.btn-secondary {  
    color: #fff;  
    background-color: var(--secondary-color);  
    border-color: var(--secondary-color);  
}  

.btn-success {  
    color: #fff;  
    background-color: var(--success-color);  
    border-color: var(--success-color);  
}  

.btn-danger {  
    color: #fff;  
    background-color: var(--danger-color);  
    border-color: var(--danger-color);  
}  

.btn-info {  
    color: #fff;  
    background-color: var(--info-color);  
    border-color: var(--info-color);  
}  

/* Panel heading */  
.panel-heading {  
    margin-bottom: 20px;  
}  

.panel-heading h1,   
.panel-heading h2,   
.panel-heading h3 {  
    margin: 0 0 10px 0;  
    font-weight: 500;  
}  

/* Mobile Responsive */  
@media (max-width: 768px) {  
    #sidebar {  
        margin-left: -250px;  
    }  
    
    #sidebar.active {  
        margin-left: 0;  
    }  
    
    #content {  
        width: 100%;  
        margin-left: 0;  
    }  
    
    #content.active {  
        margin-left: 250px;  
        width: calc(100% - 250px);  
    }  
    
    #sidebarCollapse span {  
        display: none;  
    }  
    
    .stat-circle {  
        width: 80px;  
        height: 80px;  
    }  
    
    .stat-value {  
        font-size: 20px;  
    }  
    
    .user-info .user-name {  
        display: none;  
    }  
    
    .user-info .user-role {  
        display: none;  
    }  
}  

/* Dark Mode - jQuery UI components */  
[data-bs-theme="dark"] .ui-widget-content {  
    background: #343a40;  
    color: #e9ecef;  
}  

[data-bs-theme="dark"] .ui-widget-header {  
    background: #2c3034;  
    color: #e9ecef;  
    border-color: #495057;  
}  

[data-bs-theme="dark"] .ui-state-default,   
[data-bs-theme="dark"] .ui-widget-content .ui-state-default {  
    background: #343a40;  
    color: #e9ecef;  
    border-color: #495057;  
}  

[data-bs-theme="dark"] .ui-state-hover,  
[data-bs-theme="dark"] .ui-widget-content .ui-state-hover {  
    background: #495057;  
    color: #e9ecef;  
}  

[data-bs-theme="dark"] .ui-state-active,  
[data-bs-theme="dark"] .ui-widget-content .ui-state-active {  
    background: var(--primary-color);  
    color: #ffffff;  
}  

/* Dark theme form elements */  
[data-bs-theme="dark"] input[type="text"],  
[data-bs-theme="dark"] input[type="password"],  
[data-bs-theme="dark"] input[type="email"],  
[data-bs-theme="dark"] input[type="number"],  
[data-bs-theme="dark"] input[type="date"],  
[data-bs-theme="dark"] select,  
[data-bs-theme="dark"] textarea {  
    background-color: #343a40;  
    color: #e9ecef;  
    border-color: #495057;  
}  

[data-bs-theme="dark"] input[type="text"]:focus,  
[data-bs-theme="dark"] input[type="password"]:focus,  
[data-bs-theme="dark"] input[type="email"]:focus,  
[data-bs-theme="dark"] input[type="number"]:focus,  
[data-bs-theme="dark"] input[type="date"]:focus,  
[data-bs-theme="dark"] select:focus,  
[data-bs-theme="dark"] textarea:focus {  
    background-color: #343a40;  
    color: #e9ecef;  
    border-color: var(--primary-color);  
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);  
}  

/* Pagination */  
.pagination {  
    display: flex;  
    padding-left: 0;  
    list-style: none;  
    border-radius: 0.25rem;  
}  

.page-item:first-child .page-link {  
    margin-left: 0;  
    border-top-left-radius: 0.25rem;  
    border-bottom-left-radius: 0.25rem;  
}  

.page-item:last-child .page-link {  
    border-top-right-radius: 0.25rem;  
    border-bottom-right-radius: 0.25rem;  
}  

.page-item.active .page-link {  
    z-index: 1;  
    color: #fff;  
    background-color: var(--primary-color);  
    border-color: var(--primary-color);  
}  

.page-item.disabled .page-link {  
    color: #6c757d;  
    pointer-events: none;  
    cursor: auto;  
    background-color: #fff;  
    border-color: #dee2e6;  
}  

.page-link {  
    position: relative;  
    display: block;  
    padding: 0.5rem 0.75rem;  
    margin-left: -1px;  
    line-height: 1.25;  
    color: var(--primary-color);  
    background-color: #fff;  
    border: 1px solid #dee2e6;  
}  

.page-link:hover {  
    z-index: 2;  
    color: #0056b3;  
    text-decoration: none;  
    background-color: #e9ecef;  
    border-color: #dee2e6;  
}  

[data-bs-theme="dark"] .page-link {  
    background-color: #343a40;  
    border-color: #495057;  
    color: #adb5bd;  
}  

[data-bs-theme="dark"] .page-link:hover {  
    background-color: #495057;  
    color: #fff;  
}  

[data-bs-theme="dark"] .page-item.disabled .page-link {  
    background-color: #343a40;  
    border-color: #495057;  
    color: #6c757d;  
}

.dropzone-container {  
    border: 2px dashed #dee2e6;  
    border-radius: 8px;  
    padding: 30px;  
    text-align: center;  
    transition: all 0.3s ease;  
    background-color: #f8f9fa;  
}  

.dropzone-container.highlight {  
    border-color: #0d6efd;  
    background-color: rgba(13, 110, 253, 0.05);  
}  

.file-preview-list {  
    max-height: 200px;  
    overflow-y: auto;  
}  

.file-preview-item {  
    display: flex;  
    align-items: center;  
    padding: 8px 12px;  
    margin-bottom: 8px;  
    background-color: #f8f9fa;  
    border-radius: 4px;  
    border: 1px solid #dee2e6;  
}  

.file-preview-item .file-icon {  
    margin-right: 10px;  
    color: #6c757d;  
}  

.file-preview-item .file-info {  
    flex-grow: 1;  
}  

.file-preview-item .file-name {  
    font-weight: 500;  
    margin-bottom: 2px;  
    word-break: break-all;  
}  

.file-preview-item .file-size {  
    font-size: 12px;  
    color: #6c757d;  
}  

.file-preview-item .file-remove {  
    color: #dc3545;  
    cursor: pointer;  
    padding: 4px;  
}  

.upload-icon {  
    opacity: 0.7;  
}  


/* Estilos para o DataTables */  
.dataTables_wrapper {  
    padding: 0;  
    margin-top: 1rem;  
}  

.dataTables_wrapper .dataTables_length,   
.dataTables_wrapper .dataTables_filter,   
.dataTables_wrapper .dataTables_info,   
.dataTables_wrapper .dataTables_processing,   
.dataTables_wrapper .dataTables_paginate {  
    margin-bottom: 15px;  
    color: #6c757d;  
}  

.dataTables_wrapper .dataTables_paginate .paginate_button {  
    padding: 0.3rem 0.6rem;  
    margin-left: 2px;  
    border-radius: 4px;  
}  

.dataTables_wrapper .dataTables_length select {  
    padding: 0.375rem 2.25rem 0.375rem 0.75rem;  
    border-radius: 0.25rem;  
    border: 1px solid #ced4da;  
}  

[data-bs-theme="dark"] .dataTables_wrapper .dataTables_length select {  
    border: 1px solid #495057;  
}  

.dataTables_wrapper .dataTables_filter input {  
    padding: 0.375rem 0.75rem;  
    border-radius: 0.25rem;  
    border: 1px solid #ced4da;  
    margin-left: 5px;  
}  

table.dataTable {  
    width: 100% !important;  
    margin-bottom: 1rem;  
    clear: both;  
}  

table.dataTable thead th, table.dataTable thead td {  
    padding: 12px 10px;  
    border-bottom: 1px solid #dee2e6;  
}  

table.dataTable tbody td {  
    padding: 10px;  
    vertical-align: middle;  
}  

.table-responsive {  
    margin-bottom: 1rem;  
}  

/* Ajustes para botões e badges */  
.btn-sm {  
    margin-right: 3px;  
}  

.badge {  
    font-size: 85%;  
    padding: 5px 8px;  
}  

.btn-group .btn.active {  
        background-color: #0d6efd;  
        color: white;  
    }  
    
    #filtroNumeroSelo::placeholder {  
        opacity: 0.7;  
    }  
    
    .dataTables_length select {  
        padding: 0.375rem 2.25rem 0.375rem 0.75rem;  
        border-radius: 0.25rem;  
        border: 1px solid #ced4da;  
    }  
    
    /* Espaçamento entre elementos de controle */  
    .dataTables_wrapper .row + .row {  
        margin-top: 1rem;  
    }  
    
    /* Ajustes na área de filtro */  
    .card-header.bg-white.p-3 {  
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);  
        margin-bottom: 1rem;  
    }  

.hover-effect {  
    transition: transform 0.3s ease, box-shadow 0.3s ease;  
}  
.hover-effect:hover {  
    transform: translateY(-5px);  
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;  
    cursor: pointer;  
}  

.bg-primary-light { background-color: rgba(13, 110, 253, 0.1); }  
.bg-success-light { background-color: rgba(40, 167, 69, 0.1); }  
.bg-warning-light { background-color: rgba(255, 193, 7, 0.1); }  
.bg-info-light { background-color: rgba(13, 202, 240, 0.1); }  
.bg-danger-light { background-color: rgba(220, 53, 69, 0.1); }

    .icon-sm {  
        width: 16px;  
        height: 16px;  
    }  
    .btn .icon-sm {  
        vertical-align: text-bottom;  
    }  



    /* Estilo para o toggle de tema */  
.theme-toggle-btn {  
    cursor: pointer;  
    border: none;  
    background: transparent;  
    color: var(--bs-light);  
    transition: all 0.3s ease;  
}  

.theme-toggle-track {  
    position: relative;  
    display: inline-block;  
    width: 44px;  
    height: 24px;  
    background-color: #375a7f;  
    border-radius: 12px;  
    transition: background-color 0.3s ease;  
}  

.theme-toggle-thumb {  
    position: absolute;  
    top: 2px;  
    left: 2px;  
    width: 20px;  
    height: 20px;  
    background-color: white;  
    border-radius: 50%;  
    transition: transform 0.3s ease;  
}  

/* Ícones dentro do toggle */  
.theme-toggle-icon {  
    position: absolute;  
    top: 4px;  
    width: 16px;  
    height: 16px;  
    transition: opacity 0.3s ease;  
}  

.theme-toggle-icon-light {  
    left: 4px;  
    opacity: 0;  
}  

.theme-toggle-icon-dark {  
    right: 4px;  
    opacity: 1;  
}  

/* Estado ativo (modo escuro) */  
[data-bs-theme='dark'] .theme-toggle-track {  
    background-color: #4a88d0;  
}  

[data-bs-theme='dark'] .theme-toggle-thumb {  
    transform: translateX(20px);  
}  

[data-bs-theme='dark'] .theme-toggle-icon-light {  
    opacity: 1;  
}  

[data-bs-theme='dark'] .theme-toggle-icon-dark {  
    opacity: 0;  
}  

/* Efeitos de hover */  
.theme-toggle-btn:hover .theme-toggle-track {  
    filter: brightness(1.1);  
}  

/* Animação para o texto */  
#theme-text {  
    transition: all 0.3s ease;  
}
</style>  