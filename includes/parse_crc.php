<?php  
/* parse_crc.php - Código completo com correção para tipo de livro e tratamento de aspas */  

function parseComunicacaoCRC(string $t): ?array  
{  
    /* 1) Tipo ---------------------------------------------------- */  
    if (preg_match('/Comunica[çc][ãa]o\s+de\s+(Casamento|Casamento Civil|Casamento Religioso|Óbito|Altera[çc][oõ]es de Estado Civil|Interdi[çc][ãa]o|Curatela)/iu', $t, $m)) {  
        $tipo = mb_strtolower(trim($m[1]));  
        
        // Padronizar os tipos  
        if ($tipo == 'alterações de estado civil') $tipo = 'alteração de estado civil';  
        if ($tipo == 'interdição') $tipo = 'interdição';  
    } else {  
        $tipo = null;  
    }  

    /* 2) Código --------------------------------------------------- */  
    preg_match('/C[óo]digo\s+da\s+comunica[çc][ãa]o:?\s*(\d+)/iu', $t, $m);  
    $codigo = isset($m[1]) ? trim($m[1]) : null;  

    /* 3) Cartórios de origem e destino ---------------------------- */  
    if (preg_match('/\n\s*(.+?)\s*\n\s*Ao\s+(.+?)(?:\s*\n|\s*-\s*)/iu', $t, $m)) {  
        $cart_origem = trim($m[1]);  
        $cart_destino = trim($m[2]);  
    } else {  
        $cart_origem = $cart_destino = null;  
    }  

    // Procurar por padrões alternativos para cartório destino  
    if (!$cart_destino && preg_match('/Ao\s+(.+?)(?:\s*\n|\s*-\s*)/iu', $t, $m)) {  
        $cart_destino = trim($m[1]);  
    }  

    // Procurar por padrões alternativos para cartório origem  
    if (!$cart_origem && preg_match('/^(.*?)\s*\n\s*Ao\s+/iu', $t, $m)) {  
        $cart_origem = trim($m[1]);  
    }  
    
    // Escapar aspas nos cartórios
    if ($cart_origem) {
        $cart_origem = str_replace(["'", '"'], ["\'", '\"'], $cart_origem);
    }
    if ($cart_destino) {
        $cart_destino = str_replace(["'", '"'], ["\'", '\"'], $cart_destino);
    }

    /* 4) IMPORTANTE: NÃO extrair dados do assento do primeiro parágrafo */  
    // Estes dados serão extraídos APENAS do segundo parágrafo (Ele/Ela)
    $data_assento = $livro_tipo = $livro_num = $folha = $termo = null;  

    /* 5) Nomes --------------------------------------------------- */  
    $nome_principal = $nome_conjuge = null;  

    // Função auxiliar para unir nomes que podem estar quebrados em linhas  
    $unirNomeQuebrado = function($nome) use ($t) {  
        if (!$nome) return null;  
        
        // Verificar se o nome está no final de uma linha  
        if (preg_match('/\b' . preg_quote($nome, '/') . '\s*\n\s*([A-Z][A-ZÀ-Ú\s]+?)(?:,|\s+[oa]\s+qual)/iu', $t, $match)) {  
            return trim($nome . ' ' . $match[1]);  
        }  
        return $nome;  
    };  

    // Função para limpar nomes - remover dois pontos, espaços extras e outros caracteres indesejados  
    $limparNome = function($nome) {  
        if (!$nome) return null;  
        // Remover ":" no início do nome  
        $nome = ltrim($nome, ": ");  
        // Remover múltiplos espaços  
        $nome = preg_replace('/\s+/', ' ', trim($nome));  
        // Corrigir espaços após a primeira letra (ex: "F RANCISCO" -> "FRANCISCO")  
        $nome = preg_replace('/^([A-Z])\s+([A-Z])/', '$1$2', $nome);  
        // Escapar apóstrofos e aspas para evitar problemas com SQL  
        $nome = str_replace(["'", '"'], ["\'", '\"'], $nome);  
        return $nome;  
    };  

    // IMPORTANTE: Detectar antecipadamente se há "Ela" no segundo parágrafo  
    $tem_ela_no_segundo_paragrafo = (preg_match('/(Ela)\s+(?:foi\s+)?(?:registrada|casada)\s+n?[oa](?:s|sse)\s+registro\s+civil/iu', $t) ||   
                                    preg_match('/^(Ela)\s+registrada/im', $t) ||  
                                    preg_match('/^(Ela)\s+foi\s+casada/im', $t));  

    if ($tipo === 'alteração de estado civil') {  
        // Padrão específico para averbação de divórcio com CONFORME ESCRITURA  
        if (preg_match('/foi\s+averbado\s+o\s+div[óo]rcio.*?de\s+([^,\n]+)\s+e\s+([^,\n]+)(?:,|\s+CONFORME)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
            $nome_conjuge = $limparNome($m[2]);  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Padrão para comunicação de divórcio/alteração com "havia sido registrado em..."  
        elseif (preg_match('/(?:foi\s+averbado\s+o\s+div[óo]rcio|foi\s+averbado\s+o\s+div[óo]rcio\s+no\s+termo\s+de\s+casamento).*?(?:o\s+qual\s+havia\s+sido\s+registrado\s+em.*?|de\s+)([^,\n]+)\s+e\s+([^,\n]+)(?:,|\s+conforme)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
            $nome_conjuge = $limparNome($m[2]);  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Padrão alternativo para alterações de estado civil  
        elseif (preg_match('/(?:de|de:)\s*:?\s*([^,\n]+)\s+e\s+([^,\n]+)(?:,|\s+conforme)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
            $nome_conjuge = $limparNome($m[2]);  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Padrão para casos com nome em maiúsculas  
        elseif (preg_match('/(?:de|de:)\s*:?\s*([A-Z][A-Z\s]+)\s+e\s+([A-Z][A-Z\s]+)(?:,|\s+conforme)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
            $nome_conjuge = $limparNome($m[2]);  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
    }  
    elseif ($tipo === 'casamento' || $tipo === 'casamento religioso' || $tipo === 'casamento civil') {  
        // Padrão para quando os nomes aparecem após "foi lavrado o assento de casamento civil de:"  
        if (preg_match('/(?:foi\s+lavrado\s+o\s+(?:assento|registro)\s+de\s+casamento(?:\s+civil|\s+religioso(?:s)?|\s+religioso(?:s)?\s+com\s+efeito\s+civil)?|(?:assento|registro)\s+de\s+casamento(?:\s+civil|\s+religioso(?:s)?|\s+religioso(?:s)?\s+com\s+efeito\s+civil)?)\s+de:?\s*([^,\n]+),\s*o\s+qual.*?\s+e\s+([^,\n]+),\s*a\s+qual/ius', $t, $m)) {  
            $nome_principal = $limparNome($m[1]); // Homem  
            $nome_conjuge = $limparNome($m[2]);   // Mulher  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Padrão específico para o formato "nome, o qual continuou com o mesmo nome, e nome, a qual continuou com o mesmo nome"  
        elseif (preg_match('/(?:de|de:)\s*:?\s*([^,\n]+),\s*o\s+qual\s+continuou\s+com\s+o\s+mesmo\s+nome,?\s+e\s+([^,\n]+),?\s*a\s+qual\s+continuou\s+com\s+o\s+mesmo\s+nome/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  // Homem  
            $nome_conjuge = $limparNome($m[2]);    // Mulher  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Variação do padrão acima sem vírgula antes do 'e'  
        elseif (preg_match('/(?:de|de:)\s*:?\s*([^,\n]+),\s*o\s+qual\s+continuou\s+com\s+o\s+mesmo\s+nome\s+e\s+([^,\n]+),?\s*a\s+qual\s+continuou\s+com\s+o\s+mesmo\s+nome/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  // Homem  
            $nome_conjuge = $limparNome($m[2]);    // Mulher  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Padrão simplificado para casamento civil/religioso  
        elseif (preg_match('/(?:de|de:)\s*:?\s*([^,\n]+),\s*o\s+qual.*?\s+e\s+([^,\n]+),\s*a\s+qual/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]); // Homem  
            $nome_conjuge = $limparNome($m[2]);   // Mulher  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Variação do padrão onde o nome do cônjuge pode estar no início da linha seguinte  
        elseif (preg_match('/(?:de|de:)\s*:?\s*([^,\n]+),\s*o\s+qual\s+continuou\s+com\s+o\s+mesmo\s+nome,?\s+e\s+([^,\n]+)\n([^,\n]+),?\s*a\s+qual\s+continuou/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  // Homem  
            $nome_conjuge = $limparNome($m[2] . ' ' . $m[3]);  // Mulher (nome em duas linhas)  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Padrão para o nome do cônjuge que pode estar quebrado em linhas  
        elseif (preg_match('/(?:de|de:)\s*:?\s*([^,\n]+),\s*o\s+qual.*?\s+e\s+([^,\n]+)\n([^,\n]+),\s*a\s+qual/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]); // Homem  
            $nome_conjuge = $limparNome($m[2] . ' ' . $m[3]); // Mulher (nome em duas linhas)  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Padrão específico para o formato de casamento com "nome e nome"  
        elseif (preg_match('/(?:assento|registro)\s+de\s+casamento\s*(?:civil|religioso(?:s)?)\s*(?:com\s+efeito\s+civil)?\s+de:\s*([^,\n]+)\s+e\s+([^,\n]+)(?:,|\s+[ao]s?\s+qua(?:l|is))/ius', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
            $nome_conjuge = $limparNome($m[2]);  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Padrão específico para comunicações onde os nomes são separados por "e" sem qualificação  
        elseif (preg_match('/(?:de|de:)\s*:?\s*([^,\n]+)\s+e\s+([^,\n]+)(?:,|\s+[ao]s?\s+qua(?:l|is)|\.)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
            $nome_conjuge = $limparNome($m[2]);  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        // Caso com ou sem espaço entre casamento e civil  
        elseif (preg_match('/casamento\s*(?:civil)?\s*de:\s*:?\s*(.+?),\s*(?:o|a)\s+qual.*?\s+e\s+(.+?)(?:,|\s+(?:a|o)\s+qual)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
            $nome_conjuge = $limparNome($m[2]);  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        
        // CORREÇÃO ESPECÍFICA: Se extraiu apenas um nome, tenta buscar o segundo após "e"  
        if ($nome_principal && !$nome_conjuge) {  
            if (preg_match('/\s+e\s+([^,\n]+)(?:,|\s+a\s+qual)/iu', $t, $match)) {  
                $nome_conjuge = $limparNome($match[1]);  
            }  
        }  
        
        // Padrão específico para casamento religioso - "nome, a qual continuou" ou "nome, a qual passou a assinar"  
        if (($tipo === 'casamento religioso' || $tipo === 'casamento' || $tipo === 'casamento civil') && !$nome_conjuge &&   
            preg_match('/e\s+([^,\n]+),\s*a\s+qual\s+(?:continuou|passou\s+a\s+assinar)/iu', $t, $m)) {  
            $nome_conjuge = $limparNome($m[1]);  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        
        // Extrair nome do cônjuge após "e" seguido por "a qual passou a assinar" ou "a qual continuou"  
        if (!$nome_conjuge && preg_match('/(?:e|E)\s+([A-ZÀ-Ú][A-ZÀ-Úa-zà-ú\s]+?),\s*(?:a|o)\s+qual\s+(?:continuou|passou\s+a\s+assinar)/iu', $t, $match)) {  
            $nome_conjuge = $limparNome($match[1]);  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
        
        // Padrão específico para o nome do cônjuge que pode estar em uma nova linha  
        if (!$nome_conjuge && preg_match('/\s+e\s+([A-ZÀ-Ú][A-ZÀ-Úa-zà-ú\s]+)\n([A-ZÀ-Ú][A-ZÀ-Úa-zà-ú\s]+?),\s*(?:a|o)\s+qual/iu', $t, $match)) {  
            $nome_conjuge = $limparNome($match[1] . ' ' . $match[2]);  
            
            // Se o segundo parágrafo tem "Ela", invertemos aqui  
            if ($tem_ela_no_segundo_paragrafo) {  
                $temp = $nome_principal;  
                $nome_principal = $nome_conjuge;  
                $nome_conjuge = $temp;  
            }  
        }  
    } elseif ($tipo === 'óbito') {  
        if (preg_match('/foi\s+registrado\s+o\s+(?:ó|o)bito\s+de\s+(.+?)(?:\s+ocorrido|\s+em)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
        } elseif (preg_match('/registrado\s+o\s+(?:ó|o)bito\s+de\s+(.+?)(?:\s+ocorrido|\s+em|\s+filho)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
        }  
        
        // Padrão para óbitos sem quebra entre nome e "ocorrido"  
        elseif (preg_match('/(?:ó|o)bito\s+de\s+([A-ZÀ-Ú\s]+)ocorrido/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
        }  
        
        // Melhorar extração considerando quebras de linha  
        if (!$nome_principal &&   
            preg_match('/(?:foi\s+registrado|registro)\s+(?:o|do)\s+(?:ó|o)bito\s+de\s+([^,\n]+)(?:,|\n)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
            
            // Procurar continuação do nome na próxima linha  
            if (preg_match('/(?:ó|o)bito\s+de\s+[^,\n]+\n\s*([A-Z][A-ZÀ-Ú\s]+?)(?:,|\s+ocorrido|\s+em)/iu', $t, $match)) {  
                $nome_principal .= ' ' . trim($match[1]);  
            }  
        }  
        
        // Caso específico para nomes emendados por OCR em óbitos  
        if ($nome_principal && preg_match('/^([A-ZÀ-Ú]+?)([A-Z][A-ZÀ-Úa-zà-ú\s]+)$/', $nome_principal, $m)) {  
            $nome_principal = $m[1] . ' ' . $m[2];  
        }  
    }  
    
    // Verificar padrão genérico para qualquer tipo de comunicação  
    if (!$nome_principal && !$nome_conjuge && preg_match('/de\s+([^,\n]+)\s+e\s+([^,\n]+)(?:,|\s+conforme)/iu', $t, $m)) {  
        $nome_principal = $limparNome($m[1]);  
        $nome_conjuge = $limparNome($m[2]);  
        
        // Se o segundo parágrafo tem "Ela", invertemos aqui  
        if ($tem_ela_no_segundo_paragrafo) {  
            $temp = $nome_principal;  
            $nome_principal = $nome_conjuge;  
            $nome_conjuge = $temp;  
        }  
    }  
    
    // Aplicar função para unir nomes quebrados em linhas  
    $nome_principal = $unirNomeQuebrado($nome_principal);  
    $nome_conjuge = $unirNomeQuebrado($nome_conjuge);  
    
    // Limpar os nomes uma última vez  
    $nome_principal = $limparNome($nome_principal);  
    $nome_conjuge = $limparNome($nome_conjuge);  
    
    // Garantir que o nome principal não seja nulo  
    if (!$nome_principal) {  
        // Tentativa adicional de extrair qualquer nome  
        if (preg_match('/de:\s*:?\s*([^,\n]+)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
        } elseif (preg_match('/(?:ó|o)bito\s+de\s+([^,\n]+)/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
        }  
        
        // Tentar extrair o nome completo analisando linhas adjacentes  
        if ($nome_principal && strlen($nome_principal) < 15) {  
            $linhas = explode("\n", $t);  
            foreach ($linhas as $i => $linha) {  
                if (strpos($linha, $nome_principal) !== false) {  
                    // Verificar a próxima linha para possível continuação do nome  
                    if (isset($linhas[$i+1]) && preg_match('/^([A-Z][A-ZÀ-Ú\s]+?)(?:,|\s+ocorrido|\s+em)/iu', $linhas[$i+1], $match)) {  
                        $nome_principal .= ' ' . trim($match[1]);  
                        break;  
                    }  
                }  
            }  
        }  
        
        // Tentar extrair nomes de padrões de "passou a assinar" para alterações de estado civil  
        if (!$nome_principal && preg_match('/([^,\n]+),\s*passou\s+a\s+assinar:/iu', $t, $m)) {  
            $nome_principal = $limparNome($m[1]);  
        }  
        
        // Se ainda for nulo, use um valor padrão  
        if (!$nome_principal) {  
            $nome_principal = "NOME NÃO IDENTIFICADO";  
        }  
    }  

    /* 6) Data da comunicação + Operador ------------------------- */  
    $data_comunic = null;  
    if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s*[\.,]?\s*\n?\s*Operador:/u', $t, $m)) {  
        $data_comunic = DateTime::createFromFormat('d/m/Y', $m[1])->format('Y-m-d');  
    }  
    preg_match('/Operador:\s*([^\n]+)/u', $t, $m);  
    $operador = isset($m[1]) ? trim($m[1]) : null;  
    
    // Escapar caracteres especiais no operador  
    if ($operador) {  
        $operador = str_replace(["'", '"'], ["\'", '\"'], $operador);  
    }  

    /* Extrair observações */  
    $observacoes = null;  
    if (preg_match('/OBSERVAÇÕES:\s*([^\n]+)/u', $t, $m)) {  
        $observacoes = trim($m[1]);  
        if (strtolower($observacoes) == 'em branco') {  
            $observacoes = null;  
        } else {  
            // Escapar caracteres especiais nas observações  
            $observacoes = str_replace(["'", '"'], ["\'", '\"'], $observacoes);  
        }  
    }  

    /* 7) Extrair informações do segundo parágrafo (Ele/Ela) - DADOS DO CARTÓRIO DESTINO */  
    // Determinar para quem é a anotação (Ele ou Ela)  
    $anotacao_ele_ela = null;  
    if (preg_match('/(Ele|Ela)\s+(?:foi\s+)?(?:registrad[oa]|casad[oa])\s+n?[oa](?:s|sse)\s+registro\s+civil/iu', $t, $m)) {  
        $anotacao_ele_ela = strtolower($m[1]);  
    }  
    // Se não achou com o padrão anterior, tenta um padrão mais simples  
    if (!$anotacao_ele_ela) {  
        if (preg_match('/^(Ela|Ele)\s+(?:registrada|casada|foi)/im', $t, $m)) {  
            $anotacao_ele_ela = strtolower($m[1]);  
        }  
    }  

    // IMPORTANTE: Extrair APENAS do segundo parágrafo os dados do registro
    // Incluindo o TIPO DO LIVRO (letra após "livro")
    $segundo_paragrafo_info = [];  
    
    // Padrões para extrair dados do segundo parágrafo (Ele/Ela)
    $padroes_segundo_paragrafo = [
        // Padrão completo com tipo de livro explícito
        '/(Ele|Ela)\s+(?:foi\s+)?(?:registrad[oa]|casad[oa])\s+n?[oa](?:s|sse)\s+registro\s+civil\s+(?:das\s+Pessoas\s+Naturais\s+)?(?:no|em\s+data\s+de\s+\d{2}\/\d{2}\/\d{4},?\s+no)\s+livro\s+([A-Z])(?:\-AUX)?\s+n[úu]mero\s+(\d+),\s*(?:às|as|na)\s+folh[as]{1,2}\s+(\d+),\s*(?:sob|sobre|de)\s+(?:número|nº|n\.)\s*(\d+)/iu',
        // Padrão com tipo de livro sem a palavra "número"
        '/(Ele|Ela)\s+(?:foi\s+)?(?:registrad[oa]|casad[oa])\s+n?[oa](?:s|sse)\s+registro\s+civil.*?no\s+livro\s+([A-Z])(?:\-AUX)?\s+(\d+),\s*(?:às|as|na)\s+folh[as]{1,2}\s+(\d+),\s*(?:sob|sobre|de|termo)\s+(?:número|nº|n\.|\s)*(\d+)/iu',
        // Padrão para "Ele foi casado nesse registro civil em data de..."
        '/(Ele|Ela)\s+(?:foi\s+)?casad[oa]\s+nesse\s+registro\s+civil\s+(?:em\s+data\s+de\s+\d{2}\/\d{2}\/\d{4},?\s+)?no\s+livro\s+([A-Z])(?:\-AUX)?\s+n[úu]mero\s+(\d+),\s*(?:às|as|na)\s+folh[as]{1,2}\s+(\d+),\s*(?:sob|sobre|de)\s+(?:número|nº|n\.)\s*(\d+)/iu',
        // Padrão para "Ele registrado nesse registro civil no livro..."
        '/(Ele|Ela)\s+registrad[oa]\s+nesse\s+registro\s+civil\s+no\s+livro\s+([A-Z])(?:\-AUX)?\s+n[úu]mero\s+(\d+),\s*(?:às|as|na)\s+folh[as]{1,2}\s+(\d+),\s*(?:sob|sobre|de)\s+(?:número|nº|n\.)\s*(\d+)/iu',
        // Padrão alternativo mais flexível
        '/(Ele|Ela).*?(?:registrad[oa]|casad[oa]).*?livro\s+([A-Z])(?:\-AUX)?.*?(\d+).*?folh[as]{1,2}\s+(\d+).*?(?:sob|sobre|de|termo)\s+(?:número|nº|n\.|\s)*(\d+)/ius',
        // Padrão sem tipo de livro (será inferido depois)
        '/(Ele|Ela).*?(?:registrad[oa]|casad[oa]).*?(?:sob\s+n[úu]mero\s+)?(\d+).*?folh[as]{1,2}\s+(\d+).*?(?:sob|sobre|de|termo)\s+(?:número|nº|n\.|\s)*(\d+)/ius'
    ];
    
    // Tentar cada padrão até encontrar um match
    foreach ($padroes_segundo_paragrafo as $padrao) {
        if (preg_match($padrao, $t, $m)) {
            if (count($m) == 6) { // Padrão com livro_tipo
                $segundo_paragrafo_info = [
                    'ele_ela' => strtolower($m[1]),
                    'livro_tipo' => $m[2],
                    'livro_numero' => $m[3],
                    'folha' => $m[4],
                    'termo' => $m[5]
                ];
            } else { // Padrão sem livro_tipo (será inferido)
                $segundo_paragrafo_info = [
                    'ele_ela' => strtolower($m[1]),
                    'livro_tipo' => null, // Será determinado depois
                    'livro_numero' => $m[2],
                    'folha' => $m[3],
                    'termo' => $m[4]
                ];
            }
            break;
        }
    }
    
    // Se ainda não encontrou tipo de livro, tentar extrair apenas o tipo
    if (!empty($segundo_paragrafo_info) && !$segundo_paragrafo_info['livro_tipo']) {
        if (preg_match('/(Ele|Ela).*?livro\s+([A-Z])(?:\-AUX)?/ius', $t, $m)) {
            $segundo_paragrafo_info['livro_tipo'] = $m[2];
        }
    }

    // Extrair dados de filiação  
    $filiacao = null;  
    if (preg_match('/filh[oa]\s+de\s+([^,\n]+)\s+e\s+([^,\n]+)(?:,|\s+nascid[oa])/iu', $t, $m)) {  
        $filiacao = trim($m[1] . ' e ' . $m[2]);  
    } elseif (preg_match('/filh[oa]\s+de\s+([^,\n]+)(?:,|\s+nascid[oa])/iu', $t, $m)) {  
        $filiacao = trim($m[1]);  
    } elseif (preg_match('/filh[oa]\s+de\s+([^,\n]+)\s+e\s+([^,\n]+)/iu', $t, $m)) {  
        $filiacao = trim($m[1] . ' e ' . $m[2]);  
    }  
    
    // Padrão específico para óbitos (filiação pode vir após "filho de" sem vírgula)  
    if (!$filiacao && $tipo === 'óbito') {  
        if (preg_match('/filho\s+de\s+([^,\n]+?)(?:\s+e\s+|\s+nascido)/iu', $t, $m)) {  
            $filiacao = trim($m[1]);  
            
            // Tentar encontrar o segundo nome da filiação  
            if (preg_match('/filho\s+de\s+[^,\n]+?\s+e\s+([^,\n]+?)(?:,|\s+nascido)/iu', $t, $m2)) {  
                $filiacao .= ' e ' . trim($m2[1]);  
            }  
        }  
    }  
    
    // Escapar caracteres especiais na filiação  
    if ($filiacao) {  
        $filiacao = str_replace(["'", '"'], ["\'", '\"'], $filiacao);  
    }  

    // Extrair data de nascimento  
    $data_nascimento = null;  
    if (preg_match('/nascid[oa]\s+(?:aos|em)\s+(\d{2}\/\d{2}\/\d{4})/iu', $t, $m)) {  
        $data_nascimento = DateTime::createFromFormat('d/m/Y', $m[1])->format('Y-m-d');  
    } elseif (preg_match('/nascid[oa]\s+(?:aos|em)\s+(\d{2})\/(\d{2})\/(\d{4})/iu', $t, $m)) {  
        // Formato alternativo com possíveis espaços entre os componentes da data  
        $data_nascimento = DateTime::createFromFormat('d/m/Y', $m[1].'/'.$m[2].'/'.$m[3])->format('Y-m-d');  
    }  
    
    // Tentar extrair data do primeiro parágrafo como data_assento se não foi encontrada
    if (!$data_assento) {
        // Buscar qualquer data no formato DD/MM/AAAA após "Aos"
        if (preg_match('/Aos\s+(\d{2}\/\d{2}\/\d{4})/iu', $t, $m)) {
            $data_assento = DateTime::createFromFormat('d/m/Y', $m[1])->format('Y-m-d');
        }
        // Buscar data em outros contextos
        elseif (preg_match('/em\s+(\d{2}\/\d{2}\/\d{4})/iu', $t, $m)) {
            $data_assento = DateTime::createFromFormat('d/m/Y', $m[1])->format('Y-m-d');
        }
        // Busca genérica por qualquer data
        elseif (preg_match('/(\d{2}\/\d{2}\/\d{4})/u', $t, $m)) {
            $data_assento = DateTime::createFromFormat('d/m/Y', $m[1])->format('Y-m-d');
        }
    }

    // IMPORTANTE: Usar APENAS os dados do segundo parágrafo para livro/folha/termo
    if (!empty($segundo_paragrafo_info)) {  
        $livro_tipo = $segundo_paragrafo_info['livro_tipo'];  
        $livro_num = $segundo_paragrafo_info['livro_numero'];  
        $folha = $segundo_paragrafo_info['folha'];  
        $termo = $segundo_paragrafo_info['termo'];  
    }  
    
    // Se não encontrou tipo de livro no segundo parágrafo, determinar baseado no contexto
    if (!$livro_tipo && !empty($segundo_paragrafo_info)) {
        // Se é um registro de "Ele/Ela registrado", geralmente é nascimento (Livro A)
        if (preg_match('/(Ele|Ela)\s+registrad[oa]/iu', $t)) {
            $livro_tipo = 'A';
        }
        // Se é "Ele/Ela casado", pode ser nascimento (Livro A) ou casamento (Livro B)
        elseif (preg_match('/(Ele|Ela)\s+(?:foi\s+)?casad[oa]/iu', $t)) {
            // Se a comunicação é de casamento, o registro do Ele/Ela é de nascimento
            if ($tipo === 'casamento' || $tipo === 'casamento civil' || $tipo === 'casamento religioso') {
                $livro_tipo = 'A';
            } else {
                $livro_tipo = 'B';
            }
        }
    }
    
    // Se ainda não tem tipo de livro, usar valores padrão baseados no tipo de comunicação
    if (!$livro_tipo) {
        if ($tipo === 'óbito') {
            // Para óbitos, o registro no segundo parágrafo geralmente é de nascimento
            $livro_tipo = 'A';
        } elseif ($tipo === 'casamento' || $tipo === 'casamento religioso' || $tipo === 'casamento civil') {
            // Para casamentos, o registro no segundo parágrafo é de nascimento
            $livro_tipo = 'A';
        } elseif ($tipo === 'alteração de estado civil') {
            // Para alterações, pode ser nascimento ou casamento
            $livro_tipo = 'A'; // Assumir nascimento como padrão
        } else {
            $livro_tipo = 'A'; // Padrão geral
        }
    }
    
    // Garantir que livro_tipo, livro_num, folha e termo nunca sejam nulos  
    if (!$livro_num) {  
        $livro_num = "0";
    }  
    
    if (!$folha) {  
        $folha = "0";  
    }  
    
    if (!$termo) {  
        $termo = "0";  
    }  
    
    // Escapar aspas nos valores numéricos também (por segurança)
    $livro_num = str_replace(["'", '"'], ["\'", '\"'], $livro_num);
    $folha = str_replace(["'", '"'], ["\'", '\"'], $folha);
    $termo = str_replace(["'", '"'], ["\'", '\"'], $termo);

    /* 10) Validação ---------------------------------------------- */  
    foreach (['codigo','tipo','cart_origem','cart_destino','data_comunic'] as $f) {  
        if (empty($f)) return null;  
    }  

    /* 11) Retorno ------------------------------------------------- */  
    return [  
        'tipo'                => $tipo,  
        'codigo_crc'          => $codigo,  
        'cartorio_origem'     => $cart_origem,  
        'cartorio_destino'    => $cart_destino,  
        'livro_tipo'          => $livro_tipo,  
        'livro_numero'        => $livro_num,  
        'folha'               => $folha,  
        'termo'               => $termo,  
        'data_registro'       => $data_assento,  
        'nome_principal'      => $nome_principal,  
        'nome_conjuge'        => $nome_conjuge,  
        'filiacao'            => $filiacao,  
        'data_nascimento'     => $data_nascimento,  
        'observacoes'         => $observacoes,  
        'data_comunicacao'    => $data_comunic,  
        'operador'            => $operador,  
        'texto_integral'      => $t,  
    ];  
}  

/* =================================================================  
   Divide texto em blocos ("Código da comunicação: …")  
   =================================================================*/  
function splitComunicacoesCRC(string $txt): array  
{  
    // Primeiro, divide o texto nas comunicações e em rejeições  
    $pattern = '/(?=Comunicação de (?:Casamento|Casamento Civil|Casamento Religioso|Óbito|Interdição|Curatela|Alterações de Estado Civil))/iu';  
    $blocos = preg_split($pattern, trim($txt));  
    
    // Remover blocos vazios e processar cada comunicação  
    $result = [];  
    foreach (array_filter(array_map('trim', $blocos)) as $bloco) {  
        // Verificar se tem código de comunicação  
        if (preg_match('/Código da comunicação:\s*\d+/iu', $bloco)) {  
            // Verificar se é uma rejeição  
            if (preg_match('/R\s*E\s*J\s*E\s*I\s*T\s*A\s*D\s*O/i', $bloco)) {  
                // Extrair apenas a parte antes da rejeição  
                $parts = preg_split('/R\s*E\s*J\s*E\s*I\s*T\s*A\s*D\s*O/i', $bloco, 2);  
                $result[] = trim($parts[0]);  
            } else {  
                $result[] = $bloco;  
            }  
        }  
    }  
    
    // Se não encontrou comunicações pelo padrão principal, tente outros padrões  
    if (empty($result)) {  
        // Tente dividir usando outros padrões  
        $alt_patterns = [  
            '/(?=Comunicação de Óbito)/ui',  
            '/(?=Comunicação de Casamento)/ui',  
            '/(?=Comunicação de Casamento Civil)/ui',  
            '/(?=Comunicação de Casamento Religioso)/ui',  
            '/(?=Comunicação de Interdição)/ui',  
            '/(?=Comunicação de Curatela)/ui',  
            '/(?=Comunicação de Alterações de Estado Civil)/ui',  
            // Padrões alternativos com possíveis erros de OCR  
            '/(?=Comunicaç[aã]o de Casamento)/ui',  
            '/(?=Comunicaç[aã]o de Casamento Civil)/ui',  
            '/(?=Comunicaç[aã]o de Casamento Religioso)/ui',  
            '/(?=Comunicaç[aã]o de Óbito)/ui',  
            '/(?=Comunicaç[aã]o de Alteraç[oõ]es de Estado Civil)/ui'  
        ];  
        
        foreach ($alt_patterns as $pattern) {  
            $parts = preg_split($pattern, trim($txt));  
            $filtered_parts = array_filter(array_map('trim', $parts));  
            
            if (count($filtered_parts) > 1) {  
                foreach ($filtered_parts as $part) {  
                    if (preg_match('/Código da comunicação:\s*\d+/iu', $part) ||   
                        (strpos($part, 'Código da comunicação:') !== false && preg_match('/\d{8,}/', $part))) {  
                        $result[] = $part;  
                    }  
                }  
                
                if (!empty($result)) {  
                    break;  
                }  
            }  
        }  
    }  
    
    // Se ainda não encontrou comunicações, verifique se há pelo menos uma  
    if (empty($result) &&   
        (preg_match('/Comunicação de (Óbito|Casamento|Casamento Civil|Casamento Religioso|Interdição|Curatela|Alterações de Estado Civil)/ui', $txt) ||   
         preg_match('/Aos \d{2}\/\d{2}\/\d{4} no livro [A-Z]/ui', $txt)) &&  
        (preg_match('/Código da comunicação:\s*\d+/iu', $txt) || preg_match('/Código da comunicação:\s*\d{8,}/iu', $txt))) {  
        $result[] = $txt;  
    }  
    
    // Verifica se temos comunicações cortadas que precisam ser unidas  
    if (count($result) > 1) {  
        $new_result = [];  
        $current_block = '';  
        
        foreach ($result as $block) {  
            // Se o bloco atual não tem todas as partes essenciais, acumula para o próximo  
            if (!preg_match('/Operador:/ui', $block) ||   
                !preg_match('/Observações:/ui', $block) ||   
                !preg_match('/Aos \d{2}\/\d{2}\/\d{4}/ui', $block)) {  
                if ($current_block) {  
                    $current_block .= "\n" . $block;  
                } else {  
                    $current_block = $block;  
                }  
            } else {  
                // Este bloco parece completo  
                if ($current_block) {  
                    // Adicionar o bloco acumulado anteriormente, se houver  
                    $new_result[] = $current_block;  
                    $current_block = '';  
                }  
                $new_result[] = $block;  
            }  
        }  
        
        // Adicionar o último bloco acumulado, se houver  
        if ($current_block) {  
            $new_result[] = $current_block;  
        }  
        
        $result = $new_result;  
    }  
    
    return $result;  
}  
?>