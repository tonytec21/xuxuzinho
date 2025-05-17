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
    color: #adb5bd!important;  
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
    text-align: center;  
    white-space: nowrap;  
    vertical-align: middle;  
    user-select: none;  
    font-size: 1rem;  
    line-height: 1.5;  
    border-radius: var(--border-radius);  
    padding: 0.5rem 1rem;  
    font-weight: 500;  
    display: inline-flex;  
    align-items: center;  
    justify-content: center;  
    transition: var(--transition);  
}  

.btn{box-shadow:var(--shadow-sm)}
.btn:active{transform:scale(.97)}

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

/* XZ Menu Lateral - CSS moderno e isolado */  
:root {  
  --xz-sidebar-width: 260px;  
  --xz-sidebar-bg: #2c3e50;  
  --xz-sidebar-color: #ecf0f1;  
  --xz-sidebar-hover: rgba(255, 255, 255, 0.1);  
  --xz-sidebar-active: #1abc9c;  
  --xz-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  --xz-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.14), 0 7px 10px -5px rgba(0, 0, 0, 0.1);  
  --xz-border-radius: 12px;  
}  

html[data-bs-theme="dark"] {  
  --xz-sidebar-bg: #1a1d21;  
  --xz-sidebar-hover: rgba(255, 255, 255, 0.05);  
  --xz-sidebar-active: #0d6efd;  
}  

/* Container principal para o menu */  
.xz-sidebar-container {  
  position: fixed;  
  top: 0;  
  left: 0;  
  height: 100%;  
  z-index: 1030;  
}  

/* Overlay para fechamento do menu */  
.xz-sidebar-overlay {  
  position: fixed;  
  top: 0;  
  left: 0;  
  width: 100%;  
  height: 100%;  
  background-color: rgba(0, 0, 0, 0.5);  
  backdrop-filter: blur(2px);  
  z-index: 1031;  
  opacity: 0;  
  visibility: hidden;  
  transition: var(--xz-transition);  
}  

/* Menu lateral */  
.xz-sidebar {  
  width: var(--xz-sidebar-width);  
  height: 100vh;  
  background-color: var(--xz-sidebar-bg);  
  color: var(--xz-sidebar-color);  
  display: flex;  
  flex-direction: column;  
  box-shadow: var(--xz-shadow);  
  transition: var(--xz-transition);  
  z-index: 1032;  
  position: relative;  
}  

/* Cabeçalho do menu */  
.xz-sidebar-header {  
  padding: 1.5rem;  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);  
}  

.xz-logo {  
  height: 40px;  
  max-width: 160px;  
  object-fit: contain;  
}  

.xz-sidebar-close {  
  background: transparent;  
  border: none;  
  color: var(--xz-sidebar-color);  
  cursor: pointer;  
  padding: 0;  
  display: none;  
}  

/* Conteúdo do menu (links de navegação) */  
.xz-sidebar-content {  
  flex: 1;  
  overflow-y: auto;  
  padding: 1rem 0;  
  scrollbar-width: thin;  
  scrollbar-color: rgba(255, 255, 255, 0.2) transparent;  
}  

.xz-sidebar-content::-webkit-scrollbar {  
  width: 4px;  
}  

.xz-sidebar-content::-webkit-scrollbar-track {  
  background: transparent;  
}  

.xz-sidebar-content::-webkit-scrollbar-thumb {  
  background-color: rgba(255, 255, 255, 0.2);  
  border-radius: 4px;  
}  

/* Lista de itens do menu */  
.xz-sidebar-menu {  
  list-style: none;  
  padding: 0;  
  margin: 0;  
}  

.xz-sidebar-item {  
  margin: 0.25rem 0.75rem;  
  border-radius: var(--xz-border-radius);  
  overflow: hidden;  
}  

.xz-sidebar-item.xz-active .xz-sidebar-link {  
  background-color: var(--xz-sidebar-active);  
  color: white;  
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.14), 0 7px 10px -5px rgba(26, 188, 156, 0.4);  
}  

html[data-bs-theme="dark"] .xz-sidebar-item.xz-active .xz-sidebar-link {  
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.14), 0 7px 10px -5px rgba(13, 110, 253, 0.4);  
}  

/* Links do menu */  
.xz-sidebar-link {  
  display: flex;  
  align-items: center;  
  padding: 0.75rem 1.25rem;  
  color: var(--xz-sidebar-color);  
  text-decoration: none;  
  transition: var(--xz-transition);  
  font-weight: 400;  
  letter-spacing: 0.3px;  
  border-radius: var(--xz-border-radius);  
}  

.xz-sidebar-link:hover {  
  background-color: var(--xz-sidebar-hover);  
  color: var(--xz-sidebar-color);  
}  

.xz-sidebar-link i {  
  width: 22px;  
  height: 22px;  
  stroke-width: 1.5px;  
  margin-right: 12px;  
}  

/* Rodapé do menu */  
.xz-sidebar-footer {  
  padding: 1rem 1.5rem;  
  border-top: 1px solid rgba(255, 255, 255, 0.1);  
}  

/* Botão toggle para o tema */  
.xz-theme-toggle {  
  display: flex;  
  align-items: center;  
  background: transparent;  
  color: var(--xz-sidebar-color);  
  border: none;  
  padding: 0.75rem 1rem;  
  cursor: pointer;  
  width: 100%;  
  text-align: left;  
  transition: var(--xz-transition);  
  border-radius: var(--xz-border-radius);  
}  

.xz-theme-toggle:hover {  
  background-color: var(--xz-sidebar-hover);  
}  

.xz-theme-icon {  
  display: inline-flex;  
  margin-right: 12px;  
}  

.xz-theme-icon i {  
  width: 22px;  
  height: 22px;  
  stroke-width: 1.5px;  
}  

html[data-bs-theme="light"] .xz-theme-light {  
  display: none;  
}  

html[data-bs-theme="light"] .xz-theme-dark {  
  display: inline-block;  
}  

html[data-bs-theme="dark"] .xz-theme-light {  
  display: inline-block;  
}  

html[data-bs-theme="dark"] .xz-theme-dark {  
  display: none;  
}  

/* Botão para abrir o menu em dispositivos móveis */  
.xz-sidebar-toggler {  
  position: fixed;  
  top: 1rem;  
  right: 1rem;  
  z-index: 1029;  
  background-color: var(--xz-sidebar-bg);  
  color: var(--xz-sidebar-color);  
  border: none;  
  border-radius: 50%;  
  width: 42px;  
  height: 42px;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  cursor: pointer;  
  box-shadow: var(--xz-shadow);  
  transition: var(--xz-transition);  
}  

.xz-sidebar-toggler:hover {  
  transform: translateY(-2px);  
  box-shadow: 0 7px 14px rgba(0, 0, 0, 0.18), 0 5px 5px rgba(0, 0, 0, 0.12);  
}  

.xz-sidebar-toggler i {  
  width: 24px;  
  height: 24px;  
  stroke-width: 2px;  
}  

/* Estilos para dispositivos móveis */  
@media (max-width: 767.98px) {  
  .xz-sidebar {  
    position: fixed;  
    left: -280px;  
    top: 0;  
    border-radius: 0;  
  }  
  
  .xz-sidebar.xz-open {  
    left: 0;  
  }  
  
  .xz-sidebar-overlay.xz-visible {  
    opacity: 1;  
    visibility: visible;  
  }  
  
  .xz-sidebar-close {  
    display: block;  
  }  
  
  body.xz-sidebar-open {  
    overflow: hidden;  
  }  
}  

/* Ajustes para telas maiores */  
@media (min-width: 768px) {  
  .xz-sidebar-toggler {  
    display: none;  
  }  
  
  body {  
    padding-left: var(--xz-sidebar-width);  
  }  
  
  #content {  
    margin-left: 0;  
    width: 100%;  
  }  
}  


/* XZ Topbar - CSS moderno e isolado */  
:root {  
  --xz-topbar-height: 70px;  
  --xz-topbar-bg: #ffffff;  
  --xz-topbar-color: #2c3e50;  
  --xz-topbar-border: #f1f1f1;  
  --xz-avatar-bg: #1abc9c;  
}  

html[data-bs-theme="dark"] {  
  --xz-topbar-bg: #1e2227;  
  --xz-topbar-color: #f8f9fa;  
  --xz-topbar-border: #2d3035;  
  --xz-avatar-bg: #0d6efd;  
}  

/* Barra superior */  
.xz-topbar {  
  height: var(--xz-topbar-height);  
  background-color: var(--xz-topbar-bg);  
  border-bottom: 1px solid var(--xz-topbar-border);  
  position: fixed;  
  top: 0;  
  right: 0;  
  left: 0;  
  z-index: 1020;  
  transition: all 0.3s ease;  
  margin-left: var(--xz-sidebar-width);  
}  

/* Container para a barra superior */  
.xz-topbar-container {  
  height: 100%;  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  padding: 0 1.5rem;  
}  

/* Botão de toggle para o menu lateral */  
.xz-topbar-toggle {  
  width: 36px;  
  height: 36px;  
  background: transparent;  
  border: none;  
  color: var(--xz-topbar-color);  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  cursor: pointer;  
  border-radius: 8px;  
  transition: all 0.2s ease;  
}  

.xz-topbar-toggle:hover {  
  background-color: rgba(0, 0, 0, 0.05);  
}  

.xz-topbar-toggle i {  
  width: 22px;  
  height: 22px;  
  stroke-width: 1.75px;  
}  

/* Título da página */  
.xz-page-title {  
  font-size: 1.25rem;  
  font-weight: 500;  
  color: var(--xz-topbar-color);  
  margin: 0;  
  margin-left: 1rem;  
}  

/* Área de informações do usuário */  
.xz-user-area {  
  display: flex;  
  align-items: center;  
  gap: 12px;  
}  

/* Avatar do usuário */  
.xz-user-avatar {  
  width: 40px;  
  height: 40px;  
  overflow: hidden;  
  border-radius: 50%;  
  flex-shrink: 0;  
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);  
}  

.xz-user-avatar img {  
  width: 100%;  
  height: 100%;  
  object-fit: cover;  
}  

.xz-avatar-text {  
  width: 100%;  
  height: 100%;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  background-color: var(--xz-avatar-bg);  
  color: white;  
  font-weight: 600;  
  font-size: 1.125rem;  
}  

/* Informações do usuário */  
.xz-user-info {  
  display: none;  
}  

@media (min-width: 576px) {  
  .xz-user-info {  
    display: flex;  
    flex-direction: column;  
  }  
}  

.xz-user-name {  
  font-size: 0.9rem;  
  font-weight: 500;  
  margin: 0;  
  color: var(--xz-topbar-color);  
  line-height: 1.2;  
}  

.xz-user-role {  
  font-size: 0.75rem;  
  color: #7a8899;  
  margin: 0;  
  line-height: 1.2;  
}  

/* Dropdown do usuário */  
.xz-user-dropdown {  
  position: relative;  
}  

.xz-dropdown-toggle {  
  background: transparent;  
  border: none;  
  color: var(--xz-topbar-color);  
  width: 30px;  
  height: 30px;  
  border-radius: 50%;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  cursor: pointer;  
  transition: all 0.2s ease;  
}  

.xz-dropdown-toggle:hover {  
  background-color: rgba(0, 0, 0, 0.05);  
}  

.xz-dropdown-toggle i {  
  width: 18px;  
  height: 18px;  
  stroke-width: 2px;  
}  

/* Ajustes para o posicionamento da página */  
#content {  
  padding-top: var(--xz-topbar-height);  
}  

/* Ajustes responsivos */  
@media (max-width: 767.98px) {  
  .xz-topbar {  
    margin-left: 0;  
    left: 0;  
  }  
  
  .xz-page-title {  
    flex: 1;  
    text-align: center;  
    margin-left: 0;  
  }  
}  

/* Estado de menu lateral recolhido */  
body.xz-sidebar-collapsed .xz-topbar {  
  margin-left: 70px;  
}  

/* Ajustes para telas muito pequenas */  
@media (max-width: 420px) {  
  .xz-topbar-container {  
    padding: 0 1rem;  
  }  
  
  .xz-page-title {  
    display: none;  
  }  
}  

/* Comportamento do menu lateral recolhido */  
body.xz-sidebar-collapsed .xz-sidebar {  
  width: 70px;  
}  

body.xz-sidebar-collapsed .xz-sidebar .xz-sidebar-link span,  
body.xz-sidebar-collapsed .xz-sidebar .xz-theme-toggle span,  
body.xz-sidebar-collapsed .xz-sidebar-header .xz-logo {  
  display: none;  
}  

body.xz-sidebar-collapsed .xz-sidebar-header {  
  justify-content: center;  
  padding: 1.5rem 0;  
}  

body.xz-sidebar-collapsed .xz-sidebar-link {  
  justify-content: center;  
  padding: 0.75rem;  
}  

body.xz-sidebar-collapsed .xz-sidebar-link i {  
  margin-right: 0;  
}  

body.xz-sidebar-collapsed .xz-theme-toggle {  
  justify-content: center;  
  padding: 0.75rem;  
}  

body.xz-sidebar-collapsed .xz-theme-icon {  
  margin-right: 0;  
}  

body.xz-sidebar-collapsed #content {  
  margin-left: 70px;  
  width: calc(100% - 70px);  
}  

/* Correções de layout e fixação dos elementos em tela */  
@media (min-width: 768px) {  
  body {  
    padding-left: 0;  
  }  
  
  #content {  
    padding-top: var(--xz-topbar-height);  
    min-height: 100vh;  
    width: calc(100% - var(--xz-sidebar-width));  
    margin-left: var(--xz-sidebar-width);  
    transition: var(--xz-transition);  
  }  
  
  body.xz-sidebar-collapsed #content {  
    width: calc(100% - 70px);  
    margin-left: 70px;  
  }  
}  

/* Adaptação para dispositivos móveis */  
@media (max-width: 767.98px) {  
  #content {  
    width: 100%;  
    margin-left: 0;  
    padding-top: var(--xz-topbar-height);  
    min-height: 100vh;  
  }  
  
  .xz-topbar {  
    width: 100%;  
    margin-left: 0;  
  }  
  
  body.xz-sidebar-open #content {  
    margin-left: 0;  
    width: 100%;  
    overflow: hidden;  
  }  
}  

/* Corrige overflow de conteúdo */  
.main-container {  
  width: 100%;  
  padding: 1.5rem;  
  overflow-x: hidden;  
}  

/* Assegura que as tabelas não quebrem o layout */  
.table-responsive {  
  overflow-x: auto;  
  -webkit-overflow-scrolling: touch;  
}  

/* Corrigir as áreas de card para não ultrapassarem o container */  
.card {  
  overflow: hidden;  
  width: 100%;  
}  

/* Dashboard tiles */  
.dashboard-tile {  
  background-color: white;  
  border-radius: 10px;  
  padding: 1.5rem;  
  box-shadow: 0 3px 10px rgba(0,0,0,0.05);  
  height: 100%;  
  transition: all 0.3s ease;  
}  

[data-bs-theme="dark"] .dashboard-tile {  
  background-color: #343a40;  
}  

.dashboard-tile:hover {  
  transform: translateY(-5px);  
  box-shadow: 0 8px 15px rgba(0,0,0,0.1);  
}  

.dashboard-tile .icon-wrapper {  
  width: 60px;  
  height: 60px;  
  border-radius: 12px;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  margin-bottom: 1rem;  
}  

.dashboard-tile .icon-wrapper i {  
  width: 32px;  
  height: 32px;  
  stroke-width: 1.5px;  
  color: white;  
}  

.dashboard-tile .counter {  
  font-size: 2rem;  
  font-weight: 600;  
  margin-bottom: 0.5rem;  
}  

.dashboard-tile .label {  
  color: #6c757d;  
  font-size: 0.875rem;  
}  

/* Card de perfil */  
.profile-card {  
  text-align: center;  
  padding: 2rem;  
}  

.profile-avatar {  
  width: 120px;  
  height: 120px;  
  border-radius: 50%;  
  overflow: hidden;  
  margin: 0 auto 1.5rem;  
  box-shadow: 0 5px 15px rgba(0,0,0,0.1);  
}  

.profile-avatar img {  
  width: 100%;  
  height: 100%;  
  object-fit: cover;  
}  

.profile-name {  
  font-size: 1.5rem;  
  font-weight: 600;  
  margin-bottom: 0.5rem;  
}  

.profile-role {  
  color: #6c757d;  
  margin-bottom: 1.5rem;  
}  

.profile-stats {  
  display: flex;  
  justify-content: center;  
  gap: 2rem;  
  margin-bottom: 1.5rem;  
}  

.stat-item {  
  text-align: center;  
}  

.stat-value {  
  font-size: 1.25rem;  
  font-weight: 600;  
}  

.stat-label {  
  font-size: 0.75rem;  
  color: #6c757d;  
}  

/* Cards estatísticos com cores */  
.stat-card {  
  border-radius: 10px;  
  padding: 1.5rem;  
  color: white;  
  position: relative;  
  overflow: hidden;  
  min-height: 140px;  
  display: flex;  
  flex-direction: column;  
  justify-content: space-between;  
}  

.stat-card-bg-icon {  
  position: absolute;  
  right: -15px;  
  bottom: -15px;  
  opacity: 0.2;  
  font-size: 6rem;  
}  

.stat-card-title {  
  font-size: 1rem;  
  font-weight: 500;  
  margin-bottom: 1rem;  
  position: relative;  
  z-index: 1;  
}  

.stat-card-value {  
  font-size: 2rem;  
  font-weight: 700;  
  margin-bottom: 0.5rem;  
  position: relative;  
  z-index: 1;  
}  

.stat-card-info {  
  font-size: 0.875rem;  
  font-weight: 400;  
  position: relative;  
  z-index: 1;  
}  

.bg-primary-gradient {  
  background: linear-gradient(135deg, #007bff 0%, #1e88e5 100%);  
}  

.bg-success-gradient {  
  background: linear-gradient(135deg, #28a745 0%, #43a047 100%);  
}  

.bg-warning-gradient {  
  background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);  
}  

.bg-danger-gradient {  
  background: linear-gradient(135deg, #dc3545 0%, #e53935 100%);  
}  

.bg-info-gradient {  
  background: linear-gradient(135deg, #17a2b8 0%, #00acc1 100%);  
}  

.bg-purple-gradient {  
  background: linear-gradient(135deg, #6f42c1 0%, #7b1fa2 100%);  
}  

/* Scrollbar personalizada */  
::-webkit-scrollbar {  
  width: 8px;  
  height: 8px;  
}  

::-webkit-scrollbar-track {  
  background: #f1f1f1;  
}  

::-webkit-scrollbar-thumb {  
  background: #c1c1c1;  
  border-radius: 4px;  
}  

::-webkit-scrollbar-thumb:hover {  
  background: #a8a8a8;  
}  

[data-bs-theme="dark"] ::-webkit-scrollbar-track {  
  background: #2c3034;  
}  

[data-bs-theme="dark"] ::-webkit-scrollbar-thumb {  
  background: #495057;  
}  

[data-bs-theme="dark"] ::-webkit-scrollbar-thumb:hover {  
  background: #6c757d;  
}  

/* Timeline */  
.timeline {  
  position: relative;  
  padding-left: 3rem;  
  margin-bottom: 3rem;  
}  

.timeline:before {  
  content: '';  
  position: absolute;  
  left: 0.85rem;  
  top: 0;  
  height: 100%;  
  width: 2px;  
  background: rgba(0,0,0,0.1);  
}  

[data-bs-theme="dark"] .timeline:before {  
  background: rgba(255,255,255,0.1);  
}  

.timeline-item {  
  position: relative;  
  padding-bottom: 1.5rem;  
}  

.timeline-dot {  
  position: absolute;  
  left: -2.95rem;  
  width: 1.5rem;  
  height: 1.5rem;  
  border-radius: 50%;  
  background: white;  
  border: 2px solid #dee2e6;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  color: #6c757d;  
}  

[data-bs-theme="dark"] .timeline-dot {  
  background: #343a40;  
  border-color: #495057;  
}  

.timeline-date {  
  font-size: 0.75rem;  
  color: #6c757d;  
  margin-bottom: 0.5rem;  
}  

.timeline-content {  
  background: white;  
  border-radius: 0.5rem;  
  padding: 1rem;  
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);  
}  

[data-bs-theme="dark"] .timeline-content {  
  background: #343a40;  
}  

.timeline-title {  
  font-weight: 600;  
  margin-bottom: 0.5rem;  
}  

.timeline-body {  
  color: #6c757d;  
}  

/* Botões em cards e listas */  
.action-buttons {  
  display: flex;  
  align-items: center;  
  gap: 0.5rem;  
}  

.action-button {  
  width: 32px;  
  height: 32px;  
  border-radius: 6px;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  color: #6c757d;  
  background: rgba(0,0,0,0.05);  
  border: none;  
  transition: all 0.2s ease;  
}  

[data-bs-theme="dark"] .action-button {  
  background: rgba(255,255,255,0.1);  
  color: #adb5bd;  
}  

.action-button:hover {  
  background: rgba(0,0,0,0.1);  
  color: #495057;  
}  

[data-bs-theme="dark"] .action-button:hover {  
  background: rgba(255,255,255,0.15);  
  color: #f8f9fa;  
}  

.action-button i {  
  width: 16px;  
  height: 16px;  
  stroke-width: 2px;  
}  

/* Fix para o conteúdo principal e scroll */  
html, body {  
  height: 100%;  
  overflow-x: hidden;  
}  

body {  
  display: flex;  
  flex-direction: column;  
}  

/* Ajustes finais para layouts responsivos */  
.main-content-wrapper {  
  flex: 1;  
  display: flex;  
  flex-direction: column;  
  min-height: calc(100vh - var(--xz-topbar-height));  
}  

/* Animação de loading */  
.loading-overlay {  
  position: fixed;  
  top: 0;  
  left: 0;  
  width: 100%;  
  height: 100%;  
  background: rgba(255, 255, 255, 0.8);  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  z-index: 9999;  
  transition: opacity 0.3s ease;  
}  

[data-bs-theme="dark"] .loading-overlay {  
  background: rgba(33, 37, 41, 0.8);  
}  

.loading-spinner {  
  width: 50px;  
  height: 50px;  
  border: 5px solid rgba(0, 0, 0, 0.1);  
  border-radius: 50%;  
  border-top-color: var(--primary-color);  
  animation: spin 1s ease-in-out infinite;  
}  

@keyframes spin {  
  to {  
    transform: rotate(360deg);  
  }  
}  

/* Estilos de impressão */  
@media print {  
  .xz-sidebar, .xz-topbar, .xz-sidebar-toggler {  
    display: none !important;  
  }  
  
  #content {  
    margin-left: 0 !important;  
    width: 100% !important;  
    padding-top: 0 !important;  
  }  
  
  body {  
    padding-left: 0 !important;  
  }  
  
  .no-print {  
    display: none !important;  
  }  
  
  .card {  
    box-shadow: none !important;  
    border: 1px solid #dee2e6 !important;  
  }  
  
  .dashboard-tile:hover {  
    transform: none !important;  
    box-shadow: none !important;  
  }  
}  

/* Utilitários de espaçamento adicionais */  
.p-4-5 {  
  padding: 2rem !important;  
}  

.mt-4-5 {  
  margin-top: 2rem !important;  
}  

.mb-4-5 {  
  margin-bottom: 2rem !important;  
}  

.ml-4-5 {  
  margin-left: 2rem !important;  
}  

.mr-4-5 {  
  margin-right: 2rem !important;  
}  

/* Sombras */  
.shadow-sm-hover:hover {  
  box-shadow: 0 .125rem .25rem rgba(0,0,0,.075) !important;  
}  

.shadow-hover:hover {  
  box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important;  
}  

.shadow-lg-hover:hover {  
  box-shadow: 0 1rem 3rem rgba(0,0,0,.175) !important;  
}  

/* Estilos para tabelas com funcionalidades avançadas */  
.table-advanced {  
  width: 100%;  
  border-collapse: separate;  
  border-spacing: 0;  
  border-radius: 8px;  
  overflow: hidden;  
}  

.table-advanced thead th {  
  background-color: rgba(0,0,0,0.03);  
  border-bottom: 2px solid rgba(0,0,0,0.05);  
  padding: 12px 16px;  
  font-weight: 600;  
  text-transform: uppercase;  
  font-size: 12px;  
  letter-spacing: 0.5px;  
}  

[data-bs-theme="dark"] .table-advanced thead th {  
  background-color: rgba(255,255,255,0.05);  
  border-bottom: 2px solid rgba(255,255,255,0.05);  
}  

.table-advanced tbody tr {  
  transition: all 0.2s ease;  
}  

.table-advanced tbody tr:hover {  
  background-color: rgba(0,0,0,0.02);  
}  

[data-bs-theme="dark"] .table-advanced tbody tr:hover {  
  background-color: rgba(255,255,255,0.02);  
}  

.table-advanced td {  
  padding: 12px 16px;  
  vertical-align: middle;  
  border-bottom: 1px solid rgba(0,0,0,0.05);  
}  

[data-bs-theme="dark"] .table-advanced td {  
  border-bottom: 1px solid rgba(255,255,255,0.05);  
}  

.table-advanced tbody tr:last-child td {  
  border-bottom: none;  
}  

/* Scrollbar personalizada - complemento para navegadores que suportam */  
body {  
  scrollbar-width: thin;  
  scrollbar-color: #c1c1c1 #f1f1f1;  
}  

[data-bs-theme="dark"] body {  
  scrollbar-color: #495057 #2c3034;  
}

/* Estilo para o botão de cópia */  
.copy-button {  
    background: transparent;  
    border: none;  
    cursor: pointer;  
    padding: 0.375rem 0.75rem;  
    transition: all 0.2s ease;  
    color: #6c757d;  
    outline: none;  
    display: flex;  
    align-items: center;  
}  

.copy-button:hover {  
    color: #495057;  
}  

.copy-button:active {  
    transform: scale(0.95);  
}  

.copy-button svg {  
    width: 16px;  
    height: 16px;  
}  

/* Tooltip de confirmação */  
.copy-tooltip {  
    position: absolute;  
    background-color: #28a745;  
    color: white;  
    padding: 4px 8px;  
    border-radius: 4px;  
    font-size: 12px;  
    top: -30px; /* Posicionado acima do botão */  
    left: 50%;  
    transform: translateX(-50%);  
    white-space: nowrap;  
    opacity: 0;  
    visibility: hidden;  
    transition: opacity 0.3s, visibility 0.3s;  
    pointer-events: none;  
    z-index: 10; /* Garante que o tooltip fique acima de outros elementos */  
}  

.copy-tooltip::after {  
    content: '';  
    position: absolute;  
    top: 100%;  
    left: 50%;  
    margin-left: -5px;  
    border-width: 5px;  
    border-style: solid;  
    border-color: #28a745 transparent transparent transparent;  
}  

.copy-button.copied .copy-tooltip {  
    opacity: 1;  
    visibility: visible;  
}  

/* Ajuste para tema escuro */  
[data-bs-theme="dark"] .copy-button {  
    color: #adb5bd;  
}  

[data-bs-theme="dark"] .copy-button:hover {  
    color: #e9ecef;  
}


</style>

<style>  
/* Estilos adicionais para o bloco de detalhes do livro e visualizador */  
.detail-card {  
    transition: all 0.2s ease-in-out;  
    border: 1px solid rgba(0,0,0,0.05);  
}  

.detail-card:hover {  
    background-color: #f8f9fa;  
    transform: translateY(-2px);  
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.08) !important;  
}  

.shadow-inner {  
    box-shadow: inset 0 1px 2px rgba(0,0,0,.075) !important;  
}  

.hover-lift {  
    transition: all 0.2s ease-in-out;  
}  

.hover-lift:hover {  
    transform: translateY(-3px);  
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.08) !important;  
}  

/* Estilos para o controle de zoom e visualização */  
#visualizador-pagina {  
    transition: background-color 0.3s;  
    scroll-behavior: smooth;  
}  

#visualizador-pagina.zoomed {  
    cursor: move;  
    background-color: #f8f9fa;  
}  

/* Botões soft */  
.btn-soft-primary {  
    color: #0d6efd;  
    background-color: rgba(13, 110, 253, 0.1);  
    border-color: transparent;  
}  

.btn-soft-primary:hover {  
    color: #fff;  
    background-color: #0d6efd;  
}  

.btn-soft-danger {  
    color: #dc3545;  
    background-color: rgba(220, 53, 69, 0.1);  
    border-color: transparent;  
}  

.btn-soft-danger:hover {  
    color: #fff;  
    background-color: #dc3545;  
}  

/* Estilo para os badges */  
.badge {  
    font-weight: 500;  
}  

/* Melhoria nos botões de navegação */  
.btn-primary {  
    transition: all 0.2s;  
}  

.btn-primary:hover:not(:disabled) {  
    transform: translateY(-2px);  
    box-shadow: 0 .5rem 1rem rgba(13, 110, 253,.15) !important;  
}  

.rounded-pill {  
    border-radius: 50rem !important;  
}  

/* Status badges */  
.status-badge {  
    padding: 0.35em 0.65em;  
    font-size: 0.75em;  
    font-weight: 600;  
    text-transform: uppercase;  
    letter-spacing: 0.5px;  
}  

/* Sombra específica para o visualizador */  
#imagem-pagina {  
    transition: all 0.3s ease;  
    box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);  
}  

#imagem-pagina.active {  
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important;  
}  

/* Melhorias na responsividade */  
@media (max-width: 768px) {  
    .badge {  
        font-size: 0.75rem;  
    }  
    
    #visualizador-pagina {  
        min-height: 500px;  
    }  
    
    #imagem-pagina {  
        min-height: 480px;  
    }  
}  

/* Efeito para botões clicáveis */  
.btn {  
    position: relative;  
    overflow: hidden;  
}  

.btn:after {  
    content: '';  
    position: absolute;  
    top: 50%;  
    left: 50%;  
    width: 5px;  
    height: 5px;  
    background: rgba(255, 255, 255, 0.5);  
    opacity: 0;  
    border-radius: 100%;  
    transform: scale(1, 1) translate(-50%);  
    transform-origin: 50% 50%;  
}  

.btn:focus:not(:active)::after {  
    animation: ripple 1s ease-out;  
}  

@keyframes ripple {  
    0% {  
        transform: scale(0, 0);  
        opacity: 0.5;  
    }  
    20% {  
        transform: scale(25, 25);  
        opacity: 0.3;  
    }  
    100% {  
        opacity: 0;  
        transform: scale(40, 40);  
    }  
}  
</style>

<style>  
        /* Estilos adicionais para melhorar o design */  
        .card-header {  
            border-bottom: 1px solid rgba(0,0,0,0.05);  
        }  

        .border-dashed {  
            border: 2px dashed #dee2e6 !important;  
            transition: all 0.3s ease;  
        }  

        .border-dashed:hover {  
            border-color: #6c757d !important;  
            background-color: rgba(0,0,0,0.01);  
        }  

        .dropzone-area {  
            transition: all 0.3s ease-in-out;  
        }  

        /* Estilo para quando arrastar arquivos sobre a área */  
        .dropzone-area.highlight {  
            border-color: #0d6efd !important;  
            background-color: rgba(13,110,253,0.05);  
        }  

        /* Melhorar exibição dos arquivos selecionados */  
        #file-preview-list .preview-item {  
            padding: 10px;  
            margin-bottom: 8px;  
            border-radius: 5px;  
            border: 1px solid #dee2e6;  
            background-color: #f8f9fa;  
            transition: background-color 0.2s;  
        }  

        #file-preview-list .preview-item:hover {  
            background-color: #f0f0f0;  
        }  

        #file-preview-list .preview-item:last-child {  
            margin-bottom: 0;  
        }  

        /* Efeito de hover nas linhas da tabela */  
        .table tbody tr {  
            transition: background-color 0.2s;  
        }  

        .table tbody tr:hover {  
            background-color: rgba(13,110,253,0.03);  
        }  

        /* Estilo para os botões */  
        .btn-outline-primary {  
            border-width: 1.5px;  
        }  

        .btn-outline-primary:hover {  
            box-shadow: 0 .125rem .25rem rgba(13,110,253,.2) !important;  
        }  

        .empty-state {  
            opacity: 0.6;  
        }  

        .table thead th {  
            font-weight: 600;  
            letter-spacing: 0.5px;  
            text-transform: uppercase;  
            font-size: 0.75rem;  
            color: #6c757d;  
        }  
        </style>


<style>  
/* Estilos elegantes e responsivos */  
.card-body.bg-light {  
    background-color: #f8fafc;  
    border-radius: 10px;  
}  

/* Botões de navegação */  
.btn-primary {  
    background-color: #4361ee;  
    border: none;  
    box-shadow: 0 3px 10px rgba(67, 97, 238, 0.2);  
    font-weight: 500;  
    letter-spacing: 0.3px;  
    transition: all 0.2s ease;  
}  

.btn-primary:hover:not(:disabled) {  
    background-color: #3a56d4;  
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);  
    transform: translateY(-2px);  
}  

.btn-primary:active:not(:disabled) {  
    transform: translateY(0);  
}  

.btn-primary:disabled {  
    background-color: #a1b0f8;  
    opacity: 0.7;  
}  

/* Display de navegação */  
.navigation-display {  
    position: relative;  
    padding: 0.5rem;  
    min-width: 220px;  
}  

.navigation-label {  
    transition: all 0.3s ease;  
}  

.navigation-label:hover {  
    transform: translateY(-2px);  
}  

/* Badges elegantes */  
.badge {  
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;  
    position: relative;  
    display: inline-block;  
    font-size: 0.9rem !important;  
    padding: 0.6rem 1.2rem !important;  
    font-weight: 600 !important;  
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);  
    transition: all 0.2s ease;  
    border: 1px solid rgba(67, 97, 238, 0.1);  
    min-width: 120px;  
}  

/* .badge.bg-primary {  
    background-color: rgba(67, 97, 238, 0.1) !important;  
}   */

[data-bs-theme="dark"] .card-body.bg-light {
  background-color: #343a40;
}

[data-bs-theme="dark"] .badge.text-primary {
  color: #fff!important;
}

[data-bs-theme="dark"] .text-primary {
  color: #fff!important;
}

.badge.text-primary {  
    color: #4361ee;  
}  

/* Responsividade */  
@media (max-width: 767px) {  
    .d-flex.justify-content-between {  
        flex-direction: column;  
        gap: 1rem;  
        align-items: center;  
    }  
    
    .navigation-display {  
        order: -1;  
        margin-bottom: 0.5rem;  
        width: 100%;  
    }  
    
    .btn-primary {  
        width: 100%;  
        margin: 0.25rem 0;  
    }  
    
    .badge {  
        width: 100%;  
        max-width: 300px;  
    }  
}  

@media (min-width: 768px) and (max-width: 991px) {  
    .d-flex.justify-content-between {  
        padding: 0.5rem !important;  
    }  
    
    .btn-primary {  
        padding: 0.4rem 0.8rem !important;  
        font-size: 0.9rem;  
    }  
    
    .badge {  
        padding: 0.5rem 0.8rem !important;  
        font-size: 0.85rem !important;  
    }  
}  

/* Animação sutil ao atualizar */  
@keyframes fadeInPulse {  
    0% { opacity: 0.7; transform: scale(0.98); }  
    70% { opacity: 1; transform: scale(1.03); }  
    100% { opacity: 1; transform: scale(1); }  
}  

.value-updated {  
    animation: fadeInPulse 0.5s ease-out;  
}  
</style>  