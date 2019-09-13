<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>

<body>
    <form action="cielo/efetuandoVenda" method="post">
        @csrf
        <div>
            <label for="payment">
                <label for="credito">credito <input type="radio" name="pagamento" id="credito" value='3'></label>
            </label>
            <label for="credit">
                debito
                <input type="radio" name="pagamento" id="debito" value='4'>
            </label>
            <label for="credit">
                boleto
                <input type="radio" name="pagamento" id="boleto" value='15'>
            </label>
        </div>
        <div>
            <input type="text" name="nom_comprador" id="nom_comprador" placeholder="nome comprador">
        </div>
        <div>
            <input type="text" name="num_cartao" id="num_cartao" placeholder="numero cartão" maxlength="16">
        </div>
        <div>
            <input type="text" name="validade" id="validade" placeholder="validade">
        </div>

        <div>
            <input type="number" name="cod_seguranca" id="cod_seguranca" placeholder="CVV">
        </div>

        <div>
            <input type="number" name="preco" id="validade" placeholder="VALOR COMPRA">
        </div>

        <label for="visa">Bandeira <input type="text" name="bandeira" id="visa"></label>
        
        <button type="submit">ENVIAR</button>
    </form>
</body>
</html>