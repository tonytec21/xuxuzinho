</div> <!-- Fim do content -->  
    </div> <!-- Fim do wrapper -->  

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.js"></script>  
    <script src="js/main.js"></script>  
    <!-- <script src="js/theme-switcher.js"></script>   -->
    <script>  
        feather.replace();  
    </script>  

<script>  
document.addEventListener('DOMContentLoaded', function() {  
    // Elementos do DOM  
    const sidebar = document.querySelector('.xz-sidebar');  
    const sidebarToggler = document.querySelector('.xz-sidebar-toggler');  
    const sidebarClose = document.querySelector('.xz-sidebar-close');  
    const sidebarOverlay = document.querySelector('.xz-sidebar-overlay');  
    const themeToggle = document.getElementById('xz-theme-toggle');  
    const themeText = document.getElementById('xz-theme-text');  
    
    // Função para abrir o menu em dispositivos móveis  
    function openSidebar() {  
        sidebar.classList.add('xz-open');  
        sidebarOverlay.classList.add('xz-visible');  
        document.body.classList.add('xz-sidebar-open');  
    }  
    
    // Função para fechar o menu em dispositivos móveis  
    function closeSidebar() {  
        sidebar.classList.remove('xz-open');  
        sidebarOverlay.classList.remove('xz-visible');  
        document.body.classList.remove('xz-sidebar-open');  
    }  
    
    // Evento de clique no botão de abrir menu  
    if (sidebarToggler) {  
        sidebarToggler.addEventListener('click', openSidebar);  
    }  
    
    // Evento de clique no botão de fechar menu  
    if (sidebarClose) {  
        sidebarClose.addEventListener('click', closeSidebar);  
    }  
    
    // Evento de clique no overlay  
    if (sidebarOverlay) {  
        sidebarOverlay.addEventListener('click', closeSidebar);  
    }  
    
    // Fechar o menu quando clicar em um link (exceto links para modais)  
    const sidebarLinks = document.querySelectorAll('.xz-sidebar-link:not([data-bs-toggle])');  
    sidebarLinks.forEach(link => {  
        link.addEventListener('click', function() {  
            if (window.innerWidth < 768) {  
                closeSidebar();  
            }  
        });  
    });  
    
    // Alternar o tema claro/escuro  
    if (themeToggle) {  
        themeToggle.addEventListener('click', function() {  
            const html = document.documentElement;  
            const currentTheme = html.getAttribute('data-bs-theme');  
            
            if (currentTheme === 'dark') {  
                html.setAttribute('data-bs-theme', 'light');  
                themeText.textContent = 'Modo Escuro';  
                localStorage.setItem('theme', 'light');  
            } else {  
                html.setAttribute('data-bs-theme', 'dark');  
                themeText.textContent = 'Modo Claro';  
                localStorage.setItem('theme', 'dark');  
            }  
            
            // Recarregar os ícones Feather  
            if (typeof feather !== 'undefined') {  
                feather.replace();  
            }  
        });  
    }  
    
    // Aplicar o tema salvo no localStorage  
    const savedTheme = localStorage.getItem('theme') || 'light';  
    document.documentElement.setAttribute('data-bs-theme', savedTheme);  
    
    if (themeText) {  
        themeText.textContent = savedTheme === 'dark' ? 'Modo Claro' : 'Modo Escuro';  
    }  
    
    // Inicializar os ícones Feather  
    if (typeof feather !== 'undefined') {  
        feather.replace();  
    }  
    
    // Ajustar o layout em caso de redimensionamento da janela  
    window.addEventListener('resize', function() {  
        if (window.innerWidth >= 768) {  
            closeSidebar();  
        }  
    });  
});  

// Controle do menu lateral via topbar (telas maiores)  
const topbarToggle = document.querySelector('.xz-topbar-toggle');  
if (topbarToggle) {  
    topbarToggle.addEventListener('click', function() {  
        document.body.classList.toggle('xz-sidebar-collapsed');  
        
        // Armazenar preferência do usuário  
        const isCollapsed = document.body.classList.contains('xz-sidebar-collapsed');  
        localStorage.setItem('sidebar-collapsed', isCollapsed);  
        
        // Atualizar ícones  
        if (typeof feather !== 'undefined') {  
            feather.replace();  
        }  
    });  
    
    // Restaurar estado do menu ao carregar  
    const savedState = localStorage.getItem('sidebar-collapsed');  
    if (savedState === 'true') {  
        document.body.classList.add('xz-sidebar-collapsed');  
    }  
}
</script>
</body>  
</html>