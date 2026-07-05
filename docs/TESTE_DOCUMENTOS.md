# 🧪 GUIA RÁPIDO DE TESTE - Sistema de Documentos

## 📋 Checklist de Testes

### **1️⃣ Configuração Inicial**

- [ ] Executar migração do banco de dados
  ```
  http://localhost/rhneto-proweb/includes/migrate_create_employee_documents.php
  ```
  **Esperado:** Mensagem de sucesso + estrutura da tabela

- [ ] Verificar criação do diretório
  ```
  Caminho: c:\xampp\htdocs\rhneto-proweb\uploads\documents\
  ```
  **Esperado:** Pasta existe com permissões 755

---

### **2️⃣ Testes de Upload**

#### Teste 1: Upload Básico
1. Faça login no dashboard
2. Abra qualquer funcionário (botão "Ver")
3. Role até "📎 Documentos Anexados"
4. Clique em "Adicionar"
5. Selecione:
   - Tipo: "Contrato"
   - Arquivo: Qualquer PDF pequeno
   - Descrição: "Teste de upload"
6. Clique "Enviar"

**✅ Esperado:**
- Notificação de sucesso
- Documento aparece na lista
- Ícone correto (📄 para PDF)
- Nome, tamanho e data exibidos

#### Teste 2: Validação de Tamanho
1. Tente enviar arquivo > 5MB

**✅ Esperado:**
- Mensagem de erro: "Arquivo muito grande"
- Upload bloqueado

#### Teste 3: Validação de Formato
1. Tente enviar arquivo .exe ou .zip

**✅ Esperado:**
- Mensagem de erro: "Formato de arquivo não permitido"
- Lista de formatos aceitos

#### Teste 4: Upload de Diferentes Tipos
Teste com cada extensão:
- [ ] PDF
- [ ] DOC/DOCX
- [ ] JPG/PNG
- [ ] XLS/XLSX
- [ ] TXT

**✅ Esperado:**
- Ícone correto para cada tipo
- Upload bem-sucedido

---

### **3️⃣ Testes de Visualização**

#### Teste 5: Ver Documentos no Modal
1. Adicione 3-5 documentos
2. Feche e abra novamente o modal

**✅ Esperado:**
- Todos os documentos aparecem
- Ordem correta (mais recente primeiro)
- Informações completas

#### Teste 6: Ver Documentos na Edição
1. Clique em "Editar" no funcionário
2. Role até documentos

**✅ Esperado:**
- Mesmos documentos do modal de visualização
- Botão "Adicionar Documento" funcional

---

### **4️⃣ Testes de Download**

#### Teste 7: Download de Documento
1. Clique no botão de download (📥)

**✅ Esperado:**
- Arquivo abre em nova aba
- Conteúdo correto
- Nome de arquivo preservado

---

### **5️⃣ Testes de Exclusão**

#### Teste 8: Deletar Documento
1. Clique no botão de lixeira (🗑️)
2. Confirme a exclusão

**✅ Esperado:**
- Modal de confirmação aparece
- Após confirmar: notificação de sucesso
- Documento desaparece da lista
- Arquivo físico deletado do servidor

#### Teste 9: Cancelar Exclusão
1. Clique em deletar
2. Cancele no modal

**✅ Esperado:**
- Documento permanece na lista
- Nenhuma alteração

---

### **6️⃣ Testes de Multi-Tenancy**

#### Teste 10: Isolamento por Cliente
1. Login com cliente A
2. Adicione documento ao funcionário X
3. Logout
4. Login com cliente B
5. Tente ver documentos do funcionário X

**✅ Esperado:**
- Cliente B não vê documentos do cliente A
- Acesso negado na API

---

### **7️⃣ Testes de Interface**

#### Teste 11: Responsividade
1. Redimensione a janela
2. Teste em diferentes tamanhos

**✅ Esperado:**
- Layout se adapta
- Cards de documentos responsivos
- Botões acessíveis

#### Teste 12: Ícones e Cores
Verifique cores dos ícones:
- [ ] PDF - Vermelho
- [ ] Word - Azul
- [ ] Excel - Verde
- [ ] Imagem - Roxo
- [ ] Texto - Cinza

---

### **8️⃣ Testes de Erro**

#### Teste 13: Upload sem Arquivo
1. Abra modal de upload
2. Clique "Enviar" sem selecionar arquivo

**✅ Esperado:**
- Validação HTML impede submit
- Mensagem de campo obrigatório

#### Teste 14: Upload sem Tipo
1. Selecione arquivo
2. Deixe "Tipo" vazio
3. Tente enviar

**✅ Esperado:**
- Validação impede submit
- Campo tipo é obrigatório

#### Teste 15: Servidor Offline
1. Pare o Apache
2. Tente fazer upload

**✅ Esperado:**
- Mensagem de erro de conexão
- Notificação amigável

---

### **9️⃣ Testes de Performance**

#### Teste 16: Múltiplos Documentos
1. Adicione 10+ documentos
2. Verifique carregamento

**✅ Esperado:**
- Lista carrega rapidamente
- Scroll suave
- Sem travamentos

#### Teste 17: Arquivos Grandes
1. Envie arquivo de ~4.9MB

**✅ Esperado:**
- Upload completa
- Sem timeout
- Progresso visível

---

### **🔟 Testes de Segurança**

#### Teste 18: Injeção de Código
1. Tente usar `<script>` na descrição
2. Verifique se é sanitizado

**✅ Esperado:**
- Código não executa
- Exibição segura

#### Teste 19: Caminho Direto
1. Tente acessar diretamente:
   ```
   http://localhost/rhneto-proweb/uploads/documents/doc_123_xxx.pdf
   ```

**✅ Esperado:**
- Acesso permitido (arquivos são públicos)
- Mas apenas quem tem link consegue acessar

---

## 📊 Resumo dos Testes

| Categoria | Testes | Status |
|-----------|--------|--------|
| Configuração | 2 | ⏳ |
| Upload | 4 | ⏳ |
| Visualização | 2 | ⏳ |
| Download | 1 | ⏳ |
| Exclusão | 2 | ⏳ |
| Multi-tenancy | 1 | ⏳ |
| Interface | 2 | ⏳ |
| Erros | 3 | ⏳ |
| Performance | 2 | ⏳ |
| Segurança | 2 | ⏳ |
| **TOTAL** | **21** | **0/21** |

---

## 🐛 Registro de Bugs Encontrados

| # | Descrição | Severidade | Status |
|---|-----------|------------|--------|
| - | - | - | - |

---

## ✅ Conclusão

Após completar todos os testes, marque:
- [ ] Todos os testes passaram
- [ ] Bugs críticos corrigidos
- [ ] Sistema pronto para produção

---

**Data dos Testes:** ___/___/_____  
**Testado por:** _________________  
**Versão:** 1.0
