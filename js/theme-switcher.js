document.addEventListener('DOMContentLoaded', function() {  
    // Verificar preferência salva  
    const currentTheme = localStorage.getItem('theme') || 'light';  
    document.documentElement.setAttribute('data-bs-theme', currentTheme);  
    
    // Atualizar o texto do botão  
    updateThemeText(currentTheme);  
    
    // Adicionar evento ao botão de alternar tema  
    document.getElementById('theme-toggle').addEventListener('click', function() {  
        // Obter tema atual  
        const currentTheme = document.documentElement.getAttribute('data-bs-theme');  
        
        // Alternar tema  
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';  
        
        // Aplicar novo tema com transição suave  
        document.documentElement.classList.add('theme-transition');  
        document.documentElement.setAttribute('data-bs-theme', newTheme);  
        setTimeout(() => {  
            document.documentElement.classList.remove('theme-transition');  
        }, 300);  
        
        // Salvar preferência  
        localStorage.setItem('theme', newTheme);  
        
        // Atualizar texto  
        updateThemeText(newTheme);  
    });  
    
    function updateThemeText(theme) {  
        const textElement = document.getElementById('theme-text');  
        if (textElement) {  
            // Transição suave do texto  
            textElement.style.opacity = '0';  
            setTimeout(() => {  
                textElement.textContent = theme === 'dark' ? 'Modo Claro' : 'Modo Escuro';  
                textElement.style.opacity = '1';  
            }, 150);  
        }  
    }  
});