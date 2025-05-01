document.addEventListener('DOMContentLoaded', function() {  
    const dropzone = document.getElementById('dropzoneUpload');  
    if (!dropzone) return; // Sair se o elemento não existir  
    
    const fileInput = document.createElement('input');  
    fileInput.type = 'file';  
    fileInput.multiple = true;  
    fileInput.name = 'arquivos[]';  
    fileInput.accept = '.pdf,.jpg,.jpeg,.png';  
    fileInput.style.display = 'none';  
    fileInput.setAttribute('form', 'uploadForm');  
    
    const uploadForm = document.getElementById('uploadForm');  
    if (uploadForm) {  
        uploadForm.appendChild(fileInput);  
    } else {  
        console.error('Formulário de upload não encontrado');  
        return;  
    }  
    
    const browseBtn = document.querySelector('.browse-btn');  
    const submitBtn = document.getElementById('submitUpload');  
    const previewContainer = document.getElementById('preview-container');  
    const previewList = document.getElementById('file-preview-list');  
    
    // Evento para o botão de navegação  
    if (browseBtn) {  
        browseBtn.addEventListener('click', function() {  
            fileInput.click();  
        });  
    }  
    
    // Eventos de arrastar e soltar  
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {  
        dropzone.addEventListener(eventName, preventDefaults, false);  
    });  
    
    function preventDefaults(e) {  
        e.preventDefault();  
        e.stopPropagation();  
    }  
    
    ['dragenter', 'dragover'].forEach(eventName => {  
        dropzone.addEventListener(eventName, highlight, false);  
    });  
    
    ['dragleave', 'drop'].forEach(eventName => {  
        dropzone.addEventListener(eventName, unhighlight, false);  
    });  
    
    function highlight() {  
        dropzone.classList.add('highlight');  
    }  
    
    function unhighlight() {  
        dropzone.classList.remove('highlight');  
    }  
    
    // Manipulador de soltar arquivos  
    dropzone.addEventListener('drop', handleDrop, false);  
    
    function handleDrop(e) {  
        const dt = e.dataTransfer;  
        const files = dt.files;  
        handleFiles(files);  
    }  
    
    // Manipulador de seleção de arquivos  
    fileInput.addEventListener('change', function() {  
        handleFiles(this.files);  
    });  
    
    function handleFiles(files) {  
        if (files.length > 0) {  
            if (previewContainer) previewContainer.classList.remove('d-none');  
            if (submitBtn) submitBtn.disabled = false;  
            
            // Limpar a lista de visualização se necessário  
            // if (previewList) previewList.innerHTML = '';  
            
            Array.from(files).forEach(file => {  
                // Verificar tipo de arquivo  
                const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];  
                if (!validTypes.includes(file.type)) {  
                    showToast('Tipo de arquivo não suportado: ' + file.name, 'error');  
                    return;  
                }  
                
                // Verificar tamanho do arquivo (10MB)  
                if (file.size > 10 * 1024 * 1024) {  
                    showToast('Arquivo muito grande: ' + file.name, 'error');  
                    return;  
                }  
                
                addFilePreview(file);  
            });  
        }  
    }  
    
    function addFilePreview(file) {  
        if (!previewList) return;  
        
        const item = document.createElement('div');  
        item.className = 'file-preview-item';  
        
        // Determinar o ícone com base no tipo de arquivo  
        let iconName = 'file';  
        if (file.type === 'application/pdf') {  
            iconName = 'file-text';  
        } else if (file.type.startsWith('image/')) {  
            iconName = 'image';  
        }  
        
        // Formatar o tamanho do arquivo  
        const fileSize = formatFileSize(file.size);  
        
        item.innerHTML = `  
            <div class="file-icon">  
                <i data-feather="${iconName}" style="width: 18px; height: 18px;"></i>  
            </div>  
            <div class="file-info">  
                <div class="file-name">${file.name}</div>  
                <div class="file-size">${fileSize}</div>  
            </div>  
            <div class="file-remove" data-filename="${file.name}">  
                <i data-feather="x" style="width: 16px; height: 16px;"></i>  
            </div>  
        `;  
        
        previewList.appendChild(item);  
        
        // Inicializar os ícones Feather  
        if (typeof feather !== 'undefined') {  
            feather.replace({  
                'stroke-width': 2,  
                'width': 18,  
                'height': 18  
            });  
        }  
        
        // Adicionar evento para remover o arquivo  
        const removeBtn = item.querySelector('.file-remove');  
        removeBtn.addEventListener('click', function() {  
            // Remover o arquivo do input  
            const newFileList = new DataTransfer();  
            Array.from(fileInput.files).forEach(f => {  
                if (f.name !== this.dataset.filename) {  
                    newFileList.items.add(f);  
                }  
            });  
            fileInput.files = newFileList.files;  
            
            // Remover a visualização  
            item.remove();  
            
            // Verificar se ainda há arquivos  
            if (fileInput.files.length === 0) {  
                if (previewContainer) previewContainer.classList.add('d-none');  
                if (submitBtn) submitBtn.disabled = true;  
            }  
        });  
    }  
    
    function formatFileSize(bytes) {  
        if (bytes === 0) return '0 Bytes';  
        const k = 1024;  
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];  
        const i = Math.floor(Math.log(bytes) / Math.log(k));  
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];  
    }  
    
    // Manipulador de envio do formulário  
    if (uploadForm) {  
        uploadForm.addEventListener('submit', function(e) {  
            e.preventDefault();  
            
            if (fileInput.files.length === 0) {  
                showToast('Selecione pelo menos um arquivo para enviar', 'error');  
                return;  
            }  
            
            const progressContainer = document.getElementById('progressContainer');  
            const progressBar = document.getElementById('progressBar');  
            const uploadStatus = document.getElementById('uploadStatus');  
            
            if (progressContainer) progressContainer.classList.remove('d-none');  
            if (submitBtn) submitBtn.disabled = true;  
            
            const formData = new FormData(this);  
            
            // Adicionar todos os arquivos ao FormData  
            for (let i = 0; i < fileInput.files.length; i++) {  
                formData.append('arquivos[]', fileInput.files[i]);  
            }  
            
            // Fazer o upload via AJAX  
            const xhr = new XMLHttpRequest();  
            xhr.open('POST', 'upload_anexo.php', true);  
            
            // Monitorar o progresso do upload  
            xhr.upload.addEventListener('progress', function(e) {  
                if (e.lengthComputable && progressBar) {  
                    const percentComplete = Math.round((e.loaded / e.total) * 100);  
                    progressBar.style.width = percentComplete + '%';  
                    progressBar.textContent = percentComplete + '%';  
                    if (uploadStatus) uploadStatus.textContent = `Enviando arquivos... ${formatFileSize(e.loaded)} de ${formatFileSize(e.total)}`;  
                }  
            });  
            
            // Lidar com a resposta  
            xhr.onload = function() {  
                if (xhr.status === 200) {  
                    try {  
                        const response = JSON.parse(xhr.responseText);  
                        if (response.success) {  
                            showToast(response.message, 'success');  
                            // Limpar o formulário  
                            fileInput.value = '';  
                            if (previewList) previewList.innerHTML = '';  
                            if (previewContainer) previewContainer.classList.add('d-none');  
                            // Recarregar a página após um breve atraso para mostrar os novos arquivos  
                            setTimeout(function() {  
                                window.location.reload();  
                            }, 1500);  
                        } else {  
                            showToast(response.message, 'error');  
                            if (submitBtn) submitBtn.disabled = false;  
                        }  
                    } catch (error) {  
                        showToast('Erro ao processar a resposta do servidor', 'error');  
                        if (submitBtn) submitBtn.disabled = false;  
                    }  
                } else {  
                    showToast('Erro na comunicação com o servidor: ' + xhr.status, 'error');  
                    if (submitBtn) submitBtn.disabled = false;  
                }  
                if (uploadStatus) uploadStatus.textContent = 'Upload finalizado';  
            };  
            
            // Lidar com erros  
            xhr.onerror = function() {  
                showToast('Falha na conexão com o servidor', 'error');  
                if (submitBtn) submitBtn.disabled = false;  
                if (uploadStatus) uploadStatus.textContent = 'Upload falhou';  
            };  
            
            // Enviar a requisição  
            xhr.send(formData);  
        });  
    }  
    
    // Função para exibir mensagens de toast  
    function showToast(message, type = 'info') {  
        // Verificar se o Bootstrap Toast está disponível  
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {  
            // Criar um elemento toast  
            const toastEl = document.createElement('div');  
            toastEl.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;  
            toastEl.setAttribute('role', 'alert');  
            toastEl.setAttribute('aria-live', 'assertive');  
            toastEl.setAttribute('aria-atomic', 'true');  
            
            toastEl.innerHTML = `  
                <div class="d-flex">  
                    <div class="toast-body">  
                        ${message}  
                    </div>  
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>  
                </div>  
            `;  
            
            // Adicionar o toast ao documento  
            let toastContainer = document.querySelector('.toast-container');  
            if (!toastContainer) {  
                toastContainer = document.createElement('div');  
                toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';  
                document.body.appendChild(toastContainer);  
            }  
            toastContainer.appendChild(toastEl);  
            
            // Inicializar e mostrar o toast  
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });  
            toast.show();  
            
            // Remover o toast após o fechamento  
            toastEl.addEventListener('hidden.bs.toast', function() {  
                toastEl.remove();  
            });  
        } else {  
            // Fallback para alert se o Bootstrap Toast não estiver disponível  
            if (type === 'error') {  
                alert('Erro: ' + message);  
            } else {  
                alert(message);  
            }  
        }  
    }  
});