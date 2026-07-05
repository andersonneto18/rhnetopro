# 📎 Sistema de Anexação de Documentos para Funcionários

## ✅ Funcionalidade Implementada

Sistema completo para anexar, visualizar e gerenciar documentos dos funcionários no RH Neto ProWeb.

---

## 🗂️ O que foi criado:

### 1. **Banco de Dados**
📄 **Arquivo:** `includes/migrate_create_employee_documents.php`

- Nova tabela: `employee_documents`
- Campos:
  - `id` - Identificador único
  - `employee_id` - ID do funcionário
  - `client_id` - ID do cliente (multi-tenancy)
  - `document_name` - Nome original do arquivo
  - `document_type` - Tipo (Contrato, Certidão, etc)
  - `file_path` - Caminho no servidor
  - `file_size` - Tamanho em bytes
  - `file_extension` - Extensão do arquivo
  - `uploaded_by` - Quem fez upload
  - `description` - Descrição opcional
  - `created_at`, `updated_at` - Timestamps

- Diretório: `uploads/documents/` (criado automaticamente)

### 2. **APIs REST**

#### 📤 **Upload de Documento**
`api/employees/upload_document.php`
- Upload de arquivo
- Validação de tipo e tamanho
- Salva no banco de dados
- Formatos aceitos: PDF, DOC, DOCX, JPG, JPEG, PNG, TXT, XLS, XLSX
- Tamanho máximo: 5MB

#### 📋 **Listar Documentos**
`api/employees/get_documents.php`
- Busca todos os documentos de um funcionário
- Retorna informações formatadas
- Filtra por client_id (multi-tenancy)

#### 🗑️ **Deletar Documento**
`api/employees/delete_document.php`
- Remove documento do banco de dados
- Deleta arquivo físico
- Verificação de permissões

### 3. **Interface no Dashboard**

#### 📊 Modal de Visualização
- Nova seção "📎 Documentos Anexados"
- Listagem de todos os documentos
- Botão "Adicionar" para upload
- Ícones por tipo de arquivo
- Download direto
- Botão de exclusão

#### ✏️ Modal de Edição
- Mesma funcionalidade de documentos
- Integrado ao formulário de edição
- Permite gerenciar documentos durante edição

#### 📤 Modal de Upload
- Formulário dedicado para envio
- Seleção de tipo de documento
- Campo de descrição opcional
- Upload com validação
- Preview de informações

### 4. **JavaScript**
Funções implementadas:
- `loadEmployeeDocuments()` - Carrega docs no modal de visualização
- `loadEmployeeDocumentsEdit()` - Carrega docs no modal de edição
- `openUploadDocumentModal()` - Abre modal de upload
- `closeUploadDocumentModal()` - Fecha modal
- `deleteDocument()` - Exclui documento
- `getFileIcon()` - Retorna ícone baseado na extensão

---

## 🚀 Como Usar:

### **Passo 1: Executar Migração**
Acesse no navegador:
```
http://localhost/rhneto-proweb/includes/migrate_create_employee_documents.php
```
Isso criará a tabela e o diretório necessários.

### **Passo 2: Adicionar Documentos**

#### **No Modal de Visualização:**
1. Abra um funcionário (botão "Ver")
2. Role até a seção "📎 Documentos Anexados"
3. Clique em "Adicionar"
4. Selecione:
   - Tipo de documento
   - Arquivo (máx. 5MB)
   - Descrição (opcional)
5. Clique em "Enviar"

#### **No Modal de Edição:**
1. Edite um funcionário
2. Role até "📎 Documentos Anexados"
3. Clique em "Adicionar Documento"
4. Faça o upload
5. Continue editando outros campos
6. Salve as alterações

### **Passo 3: Gerenciar Documentos**

#### **Baixar:**
- Clique no ícone de download (📥)
- Arquivo abre em nova aba

#### **Excluir:**
- Clique no ícone de lixeira (🗑️)
- Confirme a exclusão
- Documento é removido permanentemente

---

## 📋 Tipos de Documentos Disponíveis:

1. **Contrato** - Contrato de Trabalho
2. **Certidão** - Certidões diversas
3. **Comprovativo** - Comprovativo de Residência
4. **Identificação** - Documento de Identificação
5. **Certificado** - Certificado/Diploma
6. **Atestado** - Atestado Médico
7. **Outro** - Outros tipos

---

## 🎨 Recursos Visuais:

### **Ícones por Tipo:**
- 📄 PDF - `fas fa-file-pdf` (vermelho)
- 📝 Word - `fas fa-file-word` (azul)
- 📊 Excel - `fas fa-file-excel` (verde)
- 🖼️ Imagem - `fas fa-file-image` (roxo)
- 📃 Texto - `fas fa-file-alt` (cinza)

### **Layout:**
- Cards com borda azul à esquerda
- Ícone grande do tipo de arquivo
- Nome do arquivo (truncado se longo)
- Tipo e tamanho formatados
- Descrição (se houver)
- Data e usuário que enviou
- Botões de ação (download e deletar)

---

## 🔒 Segurança:

✅ Validação de extensão de arquivo  
✅ Validação de tamanho (5MB máx)  
✅ Nomes únicos (timestamp + uniqid)  
✅ Isolamento por client_id (multi-tenancy)  
✅ Verificação de permissões no delete  
✅ Caminho protegido (uploads/documents/)  
✅ Sanitização de inputs  
✅ Foreign key para integridade referencial  

---

## 📁 Estrutura de Arquivos:

```
rhneto-proweb/
├── includes/
│   └── migrate_create_employee_documents.php  ✅ Criado
├── api/employees/
│   ├── upload_document.php                     ✅ Criado
│   ├── get_documents.php                       ✅ Criado
│   └── delete_document.php                     ✅ Criado
├── admin/
│   └── dashboard.php                           ✅ Modificado
└── uploads/
    └── documents/                              ✅ Criado automaticamente
        └── doc_[id]_[timestamp]_[uniqid].[ext]
```

---

## 🐛 Troubleshooting:

### **Erro: Tabela não existe**
**Solução:** Execute o script de migração primeiro.

### **Erro: Permissão negada ao salvar arquivo**
**Solução:** Verifique permissões da pasta `uploads/documents/` (deve ser 755).

### **Erro: Arquivo muito grande**
**Solução:** 
1. Comprima o arquivo
2. Ou aumente limite no PHP:
   - `php.ini`: `upload_max_filesize = 10M`
   - `php.ini`: `post_max_size = 10M`
   - Reinicie Apache

### **Documentos não aparecem**
**Solução:**
1. Verifique console do navegador (F12)
2. Confirme que a API está retornando dados
3. Verifique se `client_id` está correto na sessão

---

## 📊 Exemplo de Uso:

```javascript
// Carregar documentos de um funcionário
loadEmployeeDocuments(123);

// Resposta da API:
{
  "success": true,
  "documents": [
    {
      "id": 1,
      "document_name": "Contrato_Trabalho.pdf",
      "document_type": "Contrato",
      "file_size": 245760,
      "file_size_formatted": "240 KB",
      "file_extension": "pdf",
      "file_path": "uploads/documents/doc_123_1736547890_abc123.pdf",
      "description": "Contrato de trabalho assinado",
      "created_at_formatted": "11/01/2026 15:30",
      "uploaded_by_name": "Admin Silva"
    }
  ]
}
```

---

## ✅ Checklist de Implementação:

- [x] Criar tabela no banco de dados
- [x] Criar API de upload
- [x] Criar API de listagem
- [x] Criar API de exclusão
- [x] Adicionar seção no modal de visualização
- [x] Adicionar seção no modal de edição
- [x] Criar modal de upload
- [x] Implementar JavaScript de gerenciamento
- [x] Adicionar validações de segurança
- [x] Testar multi-tenancy
- [x] Adicionar ícones por tipo de arquivo
- [x] Formatar tamanhos de arquivo
- [x] Adicionar confirmação de exclusão

---

## 🎉 Pronto!

O sistema de anexação de documentos está **100% funcional** e integrado ao RH Neto ProWeb!

**Data de Implementação:** 11 de Janeiro de 2026  
**Versão:** 1.0
