$(document).ready(function() {  
    $('#uploadForm').submit(function(e) {  
        e.preventDefault();  
        
        const formData = new FormData(this);  
        const fileInput = $('#arquivoUpload')[0];  
        
        // Verificar se um arquivo foi selecionado  
        if (fileInput.files.length === 0) {  
            Swal.fire({  
                icon: 'error',  
                title: 'Erro',  
                text: 'Por favor, selecione um arquivo para enviar.'  
            });  
            return;  
        }  
        
        // Verificar o tamanho do arquivo (máximo 10MB)  
        const fileSize = fileInput.files[0].size;  
        if (fileSize > 10 * 1024 * 1024) {  
            Swal.fire({  
                icon: 'error',  
                title: 'Arquivo muito grande',  
                text: 'O tamanho máximo permitido é 10MB.'  
            });  
            return;  
        }  
        
        // Verificar o tipo do arquivo  
        const fileName = fileInput.files[0].name;  
        const fileExt = fileName.split('.').pop().toLowerCase();  
        const allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];  
        
        if (!allowedTypes.includes(fileExt)) {  
            Swal.fire({  
                icon: 'error',  
                title: 'Tipo de arquivo não permitido',  
                text: 'Apenas arquivos PDF, JPG, JPEG ou PNG são aceitos.'  
            });  
            return;  
        }  
        
        // Mostrar barra de progresso  
        $('#progressContainer').removeClass('d-none');  
        
        $.ajax({  
            url: 'upload_anexo.php',  
            type: 'POST',  
            data: formData,  
            processData: false,  
            contentType: false,  
            xhr: function() {  
                const xhr = new window.XMLHttpRequest();  
                xhr.upload.addEventListener('progress', function(e) {  
                    if (e.lengthComputable) {  
                        const percentComplete = Math.round((e.loaded / e.total) * 100);  
                        $('#progressBar').css('width', percentComplete + '%');  
                        $('#progressBar').text(percentComplete + '%');  
                    }  
                }, false);  
                return xhr;  
            },  
            success: function(response) {  
                try {  
                    const result = JSON.parse(response);  
                    if (result.success) {  
                        Swal.fire({  
                            icon: 'success',  
                            title: 'Sucesso!',  
                            text: result.message,  
                            timer: 2000,  
                            showConfirmButton: false  
                        }).then(() => {  
                            // Recarregar a página para mostrar o novo anexo  
                            window.location.reload();  
                        });  
                    } else {  
                        Swal.fire({  
                            icon: 'error',  
                            title: 'Erro',  
                            text: result.message  
                        });  
                    }  
                } catch (e) {  
                    // Se não for um JSON válido, redirecionar (provavelmente a resposta é um redirecionamento)  
                    window.location.reload();  
                }  
            },  
            error: function(xhr, status, error) {  
                Swal.fire({  
                    icon: 'error',  
                    title: 'Erro',  
                    text: 'Ocorreu um erro durante o upload. Por favor, tente novamente.'  
                });  
            },  
            complete: function() {  
                // Resetar formulário e barra de progresso  
                $('#uploadForm')[0].reset();  
                $('#progressBar').css('width', '0%');  
                $('#progressBar').text('');  
                $('#progressContainer').addClass('d-none');  
            }  
        });  
    });  
});