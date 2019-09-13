<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

use Cielo\API30\Merchant;

use Cielo\API30\Ecommerce\Environment;
use Cielo\API30\Ecommerce\Sale;
use Cielo\API30\Ecommerce\CieloEcommerce;
use Cielo\API30\Ecommerce\Payment;
use Cielo\API30\Ecommerce\CreditCard;
use Cielo\API30\Ecommerce\Request\CieloRequestException;

class CieloController extends Controller
{

    private $environment;
    private $merchant;
    private $cielo;
    private $sale;
    private $payment;
    private $dadosCartao;
    private $messages;

    public function __construct( Request $request )
    {

      // Mensagens de Erros mais comuns

      $this->messages = [
        [
          "cod" => '0',
          "message"=>'Recusada favor contatar o emissor do cartão'
        ],
        [
          "cod"   => '103',
          "message" => 'Caracteres especiais não permitidos',
        ],
        [
          "cod"=>'108',
          "message" => 'Valor da transação deve ser maior que “0”'
        ],
        [
          "cod"=> '116',
          "message" => 'Loja bloqueada, entre em contato com o suporte Cielo'
        ],
        [
          "cod"=> '120',
          "message" => 'IP bloqueado por questões de segurança',
        ],
        [
          "cod"=> '123',
          "message" => 'Numero de parcelas deve ser superior a 1',
        ],
        [
          "cod"=> '129',
          "message" => 'Meio de pagamento não vinculado a loja ou Provider inválido',
        ],
        [
          "cod"=> '170',
          "message" => 'Cartão protegido não vinculado ao cadastro do lojista',
        ],
        [
          "cod"=> '171',
          "message"=>'Falha no processamento do pedido - Entre em contato com o suporte Cielo'
        ],
        [
          "cod"=>'172',
          "message"=>'Falha na validação das credenciadas enviadas',
        ],
        [
          "cod"=>'173',
          "message"=>'Meio de pagamento não vinculado ao cadastro do lojista',           
        ],
        [
          "cod"=>'183',
          "message"=>'Data de nascimento inválida ou futura'
        ],
        [
          "cod"=>'126',
          "message"=> 'Campo enviado está vazio ou inválido'
        ],
        [
          "cod"=>'308',
          "message"=>"Transação não autorizada."
        ]
      ];      

      // Set todos os dados do cartão na variavel @dadosCartao
      $this->dadosCartao = $request->all();

      // Redireciona para a rota resAutenticacao passando o paymentId
      if ( !array_key_exists('preco', $this->dadosCartao ) ) {
        return $this->resAutenticacao();
      }

      // Formatação do valor total da venda em centavos
      $this->dadosCartao['preco'] = number_format( ( float )$this->dadosCartao['preco'] * 100., 0, '.', '' );

      // Preparando o Ambiente
      $this->environment = Environment::sandbox();
      $this->merchant    = new Merchant( config('cielo.MerchantId'), config('cielo.MerchantKey') );
      $this->cielo       = new CieloEcommerce( $this->merchant, $this->environment );

      // Crie uma instância de Sale informando o ID do pedido na loja
      // Esse id de pedido está relacionado com o id_inscricao_pagamento
      $this->sale = new Sale(2345);

      // Crie uma instância de Customer informando o nome do cliente
      $this->sale->customer( $this->dadosCartao['nom_comprador'] );

      // Crie uma instância de Payment informando o valor do pagamento
      $this->payment  = $this->sale->payment( $this->dadosCartao['preco'] );

    }

    public function index()
    {
      return view('form');
    }

    public function efetuandoVenda()
    {

      // Escolhendo a função do tipo de pagamento a ser executada
      // Referente ao seu código;
        switch ( $this->dadosCartao['pagamento'] ) {
          case "3":
              $this->creditCardPayment();
              break;
          case "4":
              $this->debitCardPayment();
              break;
          case "15":
              $this->boletPayment();
              break;
          default:
            $res = [
              "status_cod"  => 500,
              "message"     => "Nenhum Banco Foi Informado."
            ];
            return json_encode($res);
            break;

          }
    }

    public function creditCardPayment()
    {

        // Crie uma instância de Credit Card utilizando os dados de teste
        // esses dados estão disponíveis no manual de integração
        $this->payment->setType( Payment::PAYMENTTYPE_CREDITCARD )
                      ->creditCard( $this->dadosCartao['cod_seguranca'], $this->dadosCartao['bandeira'] )
                      ->setExpirationDate( $this->dadosCartao['validade'] )
                      ->setCardNumber( $this->dadosCartao['num_cartao'] )
                      ->setHolder( $this->dadosCartao['nom_comprador'] );

       // Crie o pagamento na Cielo
        try {

          // Configure o SDK com seu merchant e o ambiente apropriado para criar a venda
          $this->sale = $this->cielo->createSale( $this->sale );

          // Com a venda criada na Cielo, já temos o ID do pagamento, TID e demais
          // dados retornados pela Cielo
          $paymentId  = $this->sale->getPayment()->getPaymentId();

          // Com o ID do pagamento, podemos fazer sua captura, se ela não tiver sido capturada ainda
          $this->sale = $this->cielo->captureSale( $paymentId, $this->dadosCartao['preco'], 0 );

          // Pega o link de redirecionamento a qual faz a consulta do pagamento
          $linkAcess  = $this->sale->getLinks()[0]->Href;

          // E também podemos fazer seu cancelamento, se for o caso
          // $sale = $this->cielo->cancelSale($paymentId, $this->dadosCartao['preco']);
          // dd($linkAcess);

          dd($this->sale);
          return $this->sale->jsonSerialize();

        } catch ( CieloRequestException $e ) {

          // Em caso de erros de integração, podemos tratar o erro aqui.
          // os códigos de erro estão todos disponíveis no manual de integração.
          $error = $e->getCieloError();
        
          dd($this->getError($error->getCode()));
        }
    }

    public function debitCardPayment()
    {

      // Defina a URL de retorno para que o cliente possa voltar para a loja
      // após a autenticação do cartão
      $this->payment->setReturnUrl('http://localhost:8000/cielo/resAutenticacao');

      // Crie uma instância de Debit Card utilizando os dados de teste
      // esses dados estão disponíveis no manual de integração
      $this->payment->setType( Payment::PAYMENTTYPE_DEBITCARD )
                    ->debitCard( $this->dadosCartao['cod_seguranca'], CreditCard::VISA )
                    ->setExpirationDate( $this->dadosCartao['validade'] )
                    ->setCardNumber( $this->dadosCartao['num_cartao'] )
                    ->setHolder( $this->dadosCartao['nom_comprador'] );
      $this->payment->setAuthenticate(true);

      // Crie o pagamento na Cielo
      try {

        // Configure o SDK com seu merchant e o ambiente apropriado para criar a venda
          $this->sale = $this->cielo->createSale( $this->sale );

          // Com a venda criada na Cielo, já temos o ID do pagamento, TID e demais
          // dados retornados pela Cielo
          $paymentId  = $this->sale->getPayment()->getPaymentId();

          // Utilize a URL de autenticação para redirecionar o cliente ao ambiente
          // de autenticação do emissor do cartão
          $authenticationUrl = $this->sale->getPayment()->getAuthenticationUrl();

          // Renderização da pagina de credenciamento
          // O redirecionamento do Laravel não funcionaou
          // Assim foi uma maneira que contrei no momento pra redirecionar
          echo '<script language= "JavaScript">location.href="'.$authenticationUrl.'"</script>';

          dd($this->sale->jsonSerialize());
          return $this->sale->jsonSerialize();
          // echo '<div><object id="credential_cielo_iframe" type="text/html" data="'.$authenticationUrl.'" width="800px" height="600px" style="z-index: 99999999999;position: fixed;width: 202vh;left: 3vh; top: 0vh;height: -webkit-fill-available;"></object></div>';
          // printf($authenticationUrl);
      } catch ( CieloRequestException $e ) {

        // Em caso de erros de integração, podemos tratar o erro aqui.
        // os códigos de erro estão todos disponíveis no manual de integração.
        $error = $e->getCieloError();

        dd( $this->getError($error->getCode()) );
      }
    }

    public function boletPayment()
    {

      // Crie uma instância de Customer informando o nome do cliente,
      // documento e seu endereço
      $this->sale->getCustomer()->setIdentity('00000000001')
                                ->setEmail('Pamela@gmail.com')
                                ->setIdentityType('CPF')
                                ->address()->setZipCode('22750012')
                                          ->setCountry('BRA')
                                          ->setState('RJ')
                                          ->setCity('Rio de Janeiro')
                                          ->setDistrict('Centro')
                                          ->setStreet('Av Marechal Camara')
                                          ->setNumber('123');

      // Crie uma instância de Payment informando o valor do pagamento
      $this->payment->setType( Payment::PAYMENTTYPE_BOLETO )
                    ->setAddress('Rua de Teste')
                    ->setBoletoNumber('1234')
                    ->setAssignor('Empresa de Teste')
                    ->setDemonstrative('Desmonstrative Teste')
                    ->setExpirationDate(date('d/m/Y', strtotime('+1 month')))
                    ->setIdentification('11884926754')
                    ->setInstructions('Esse é um boleto de exemplo');

      // Crie o pagamento na Cielo
      try {

          // Configure o SDK com seu merchant e o ambiente apropriado para criar a venda
          $this->sale = $this->cielo->createSale( $this->sale );

          // Com a venda criada na Cielo, já temos o ID do pagamento, TID e demais
          // dados retornados pela Cielo
          $paymentId  = $this->sale->getPayment()->getPaymentId();
          $boletoURL  = $this->sale->getPayment()->getUrl();

          // Redirecionamento para a pagina de Boleto
          // O redirecionamento do Laravel não funcionou
          // Assim foi uma maneira que contrei no momento pra redirecionar
          echo '<script language= "JavaScript">location.href="'.$boletoURL.'"</script>';

          dd( $this->$boletoURL );
          return $this->$boletoURL;

      } catch ( CieloRequestException $e ) {

        // Em caso de erros de integração, podemos tratar o erro aqui.
        // os códigos de erro estão todos disponíveis no manual de integração.
        $error = $e->getCieloError();

      dd( $this->getError( $error->getCode() ));
      }
    }

    public function resAutenticacao()
    {
      // retorna o PaymentId para verificação
      // Para saber se foi autenticado ou não:
      // Deve-se consultar o pagamento ID na url abaixo
      // {{apiQueryUrl}}/1/sales/0f8bfde3-cfa6-402b-b858-0f1782b74656

      dd( $this->dadosCartao );
      return json_encode( $this->dadosCartao );
    }

    public function getError( $cod_err )
    {
      foreach ( $this->messages as $key => $value ) 
      {
        if (  $cod_err == $this->messages[$key]['cod'] ){
          return dd($value);
          // return $value->jsonSerialize();
        }
      }
      $res = [
        "cod" => $cod_err,
        "message"=> 'Erro na transação favor entrar em contato com suporte tecnico passando o seguinte codigo : '. $cod_err 
      ];
      return $res;
    }
}