$(document).ready(function() {  
    
    // Inicializar o sidebar collapse para dispositivos móveis  
    $('#sidebarCollapse').on('click', function() {  
        $('#sidebar').toggleClass('active');  
    });  
    
    // Fechar o sidebar quando um item do menu é clicado em telas pequenas  
    $('.nav-link').on('click', function() {  
        if (window.innerWidth < 768) {  
            $('#sidebar').addClass('active');  
        }  
    });  
    
    // Fechar o sidebar quando clicar fora dele em telas pequenas  
    $(document).on('click', function(e) {  
        if (window.innerWidth < 768) {  
            if (!$(e.target).closest('#sidebar').length &&   
                !$(e.target).closest('#sidebarCollapse').length &&   
                $('#sidebar').hasClass('active') === false) {  
                $('#sidebar').addClass('active');  
            }  
        }  
    });  
    
    // Inicializar os ícones Feather  
    feather.replace();  
    
    // Inicializar os tooltips do Bootstrap  
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));  
    tooltipTriggerList.map(function(tooltipTriggerEl) {  
        return new bootstrap.Tooltip(tooltipTriggerEl);  
    });  
    
    // Manipular mensagens de erro ou sucesso em parâmetros de URL  
    const urlParams = new URLSearchParams(window.location.search);  
    
    if (urlParams.has('error')) {  
        const errorType = urlParams.get('error');  
        let errorMsg = '';  
        
        switch (errorType) {  
            case 'invalid_id':  
                errorMsg = 'ID inválido ou não fornecido.';  
                break;  
            case 'not_found':  
                errorMsg = 'Item não encontrado ou você não tem permissão para acessá-lo.';  
                break;  
            case 'db_error':  
                errorMsg = 'Ocorreu um erro de banco de dados. Por favor, tente novamente.';  
                break;  
            case 'pdf_error':  
                errorMsg = 'Erro ao gerar o documento PDF. Por favor, tente novamente.';  
                break;  
            case 'no_attachments':  
                errorMsg = 'Não há anexos para este selo. Adicione pelo menos um anexo.';  
                break;  
            default:  
                if (urlParams.has('msg')) {  
                    errorMsg = urlParams.get('msg');  
                } else {  
                    errorMsg = 'Ocorreu um erro. Por favor, tente novamente.';  
                }  
        }  
        
        Swal.fire({  
            icon: 'error',  
            title: 'Erro',  
            text: errorMsg  
        });  
    }  
    
    if (urlParams.has('success')) {  
        Swal.fire({  
            icon: 'success',  
            title: 'Sucesso!',  
            text: 'Operação realizada com sucesso!',  
            timer: 2000,  
            showConfirmButton: false  
        });  
    }  
    
    if (urlParams.has('upload')) {  
        const uploadStatus = urlParams.get('upload');  
        
        if (uploadStatus === 'success') {  
            Swal.fire({  
                icon: 'success',  
                title: 'Upload Concluído',  
                text: 'O arquivo foi enviado com sucesso!',  
                timer: a2000,  
                showConfirmButton: false  
            });  
        } else if (uploadStatus === 'error') {  
            let errorMsg = 'Erro ao fazer upload do arquivo.';  
            
            if (urlParams.has('msg')) {  
                errorMsg = urlParams.get('msg');  
            }  
            
            Swal.fire({  
                icon: 'error',  
                title: 'Erro no Upload',  
                text: errorMsg  
            });  
        }  
    }  
    
    if (urlParams.has('delete')) {  
        if (urlParams.get('delete') === 'success') {  
            Swal.fire({  
                icon: 'success',  
                title: 'Item Excluído',  
                text: 'O item foi excluído com sucesso!',  
                timer: 2000,  
                showConfirmButton: false  
            });  
        }  
    }  
});