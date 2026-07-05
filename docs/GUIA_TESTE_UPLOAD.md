# 🔧 GUIA DE TESTE - UPLOAD DE FOTO

## 📋 Passo a passo para testar:

### 1️⃣ **Acesse o Dashboard**
```
http://localhost/rhneto-proweb/admin/dashboard.php
```

### 2️⃣ **Abra o Console do Navegador**
- Pressione `F12` ou `Ctrl + Shift + I`
- Vá na aba "Console"
- Deixe aberto para ver os logs

### 3️⃣ **Teste com Página de Teste**
```
http://localhost/rhneto-proweb/tools/uploads/test-upload.html
```
- Selecione uma imagem
- Veja o preview aparecer
- Clique em "Testar Upload"
- Observe os logs na tela

### 4️⃣ **Teste no Dashboard**
1. Vá para a seção "Funcionários"
2. Clique em "Adicionar Funcionário"
3. Preencha:
   - Nome: Teste Silva
   - Cargo: Desenvolvedor
   - Departamento: TI
   - Email: teste@teste.com
   - Telefone: 123456789
   - Data de Início: 09/01/2026
4. **Na seção 📸 Foto de Perfil:**
   - Clique em "Escolher arquivo"
   - Selecione uma imagem JPG/PNG (máx. 2MB)
   - **IMPORTANTE**: Veja o preview aparecer no círculo roxo
5. Clique em "Adicionar Funcionário"

### 🔍 **O que observar no Console:**

Você deverá ver:
```
🔍 Procurando elementos de upload...
✅ Input de foto encontrado!
📸 Arquivo selecionado: [objeto File]
📁 Arquivo: {nome: "foto.jpg", tamanho: 123456, tipo: "image/jpeg"}
✅ Validações OK! Carregando preview...
🎨 Preview carregado, atualizando DOM...
✅ Preview carregado! Agora clique em "Adicionar Funcionário"
```

### 📤 **Ao enviar o formulário:**

```
📤 Enviando formulário de adicionar funcionário...
📋 FormData entries:
  name: Teste Silva
  position: Desenvolvedor
  ...
  profile_picture: [object File]
📨 Resposta recebida: 200
📦 Dados da resposta: {success: true, employee_id: 123}
✅ Funcionário adicionado com sucesso!
```

---

## ❌ **Se NÃO aparecer o preview:**

### Possíveis causas:

1. **JavaScript não carregou**
   - Verifique erros no console
  - Recarregue a página (Ctrl + F5)

2. **IDs dos elementos não batem**
   - Verifique se existe `id="add-profile-picture"`
   - Verifique se existe `id="add-avatar-preview"`

3. **Arquivo muito grande**
   - Use imagem menor que 2MB
   - Veja mensagem de erro no console

4. **Formato inválido**
   - Use apenas JPG, PNG ou GIF
   - Veja mensagem de erro

---

## 📁 **Verificar se a foto foi salva:**

1. Após adicionar o funcionário, verifique:
```
c:\xampp\htdocs\rhneto-proweb\uploads\profile\
```

2. Deve haver um arquivo como:
```
emp_1736457890_abc123.jpg
```

3. Se a pasta não existir, será criada automaticamente

---

## 🔎 **Verificar logs do PHP:**

1. Abra os logs do Apache:
```powershell
Get-Content "C:\xampp\apache\logs\error.log" -Tail 50
```

2. Procure por linhas com:
```
📸 FILES recebido:
✅ Arquivo de foto recebido!
📁 Tentando salvar em:
✅ Foto salva com sucesso:
```

---

## 🐛 **Problemas Comuns:**

### Preview não aparece:
- ✅ Limpe o cache: Ctrl + Shift + Delete
- ✅ Recarregue: Ctrl + F5
- ✅ Verifique console por erros JavaScript

### Foto não é salva:
- ✅ Verifique permissões da pasta `uploads/profile/`
- ✅ Verifique logs do PHP
- ✅ Confirme que está enviando FormData (não JSON)

### Foto não aparece na lista:
- ✅ Recarregue a página após adicionar
- ✅ Verifique se o caminho está correto na BD
- ✅ Veja se a imagem existe no servidor

---

## ✅ **Teste Rápido:**

Execute este teste simples:

1. Abra: http://localhost/rhneto-proweb/tools/uploads/test-upload.html
2. Selecione qualquer imagem
3. Veja o preview roxo virar a imagem
4. Clique em "Testar Upload"
5. Veja os logs aparecerem

Se funcionar aqui, o problema está na integração com o dashboard.
Se não funcionar, o problema é no upload PHP.

---

**Agora teste e me diga o que acontece!** 🚀
