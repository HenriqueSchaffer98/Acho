<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imobiliária não encontrada</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f9fafb; }
        .card { text-align: center; padding: 2rem; }
        h1 { color: #111827; font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { color: #6b7280; margin-bottom: 1.5rem; }
        a { display: inline-block; padding: 0.5rem 1.25rem; background: #111827; color: #fff; border-radius: 0.375rem; text-decoration: none; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Imobiliária não encontrada</h1>
        <p>O endereço acessado não corresponde a nenhuma imobiliária cadastrada.</p>
        <a href="{{ 'http://' . config('app.base_domain') }}">Voltar para {{ config('app.base_domain') }}</a>
    </div>
</body>
</html>
