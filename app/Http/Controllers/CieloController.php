<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

    public function __construct( Request $request )
    {

      // Set todos os dados do cartão na variavel @dadosCartao
      $this->dadosCartao = $request->all();

      // Formatação do valor total da venda em centavos
      $this->dadosCartao['preco'] = number_format((float)$this->dadosCartao['preco']*100., 0, '.', '');

      // Preparando o Ambiente
      $this->environment = Environment::sandbox();
      $this->merchant    = new Merchant(config('cielo.MerchantId'), config('cielo.MerchantKey'));
      $this->cielo       = new CieloEcommerce($this->merchant, $this->environment);

      // Crie uma instância de Sale informando o ID do pedido na loja
      // Esse id de pedido está relacionado com o id_inscricao_pagamento
      $this->sale = new Sale(2345);

      // Crie uma instância de Customer informando o nome do cliente
      $this->sale->customer($this->dadosCartao['nom_comprador']);

      // Crie uma instância de Payment informando o valor do pagamento
      $this->payment = $this->sale->payment($this->dadosCartao['preco']);

    }

    public function index()
    {
      return view('form');
    }

    public function efetuandoVenda()
    {
        switch ($this->dadosCartao['pagamento']) {
          case "3":
              $this->creditCardPayment();
              break;
          case "4":
              $this->debitCardPayment();
              break;
          // case "15":
          //     $this->payment = Payment::PAYMENTTYPE_BOLETO;
          //     $this->boletPayment();
          //     break;
          default:
            return false;
            break;
      }
    }

    public function creditCardPayment()
    {

        // Crie uma instância de Credit Card utilizando os dados de teste
        // esses dados estão disponíveis no manual de integração
        $this->payment->setType( Payment::PAYMENTTYPE_CREDITCARD )
                ->creditCard( $this->dadosCartao['cod_seguranca'], CreditCard::VISA )
                ->setExpirationDate( $this->dadosCartao['validade'] )
                ->setCardNumber( $this->dadosCartao['num_cartao'] )
                ->setHolder( $this->dadosCartao['nom_comprador'] );

       // Crie o pagamento na Cielo
        try {

          // Configure o SDK com seu merchant e o ambiente apropriado para criar a venda
          $this->sale = $this->cielo->createSale($this->sale);

          // Com a venda criada na Cielo, já temos o ID do pagamento, TID e demais
          // dados retornados pela Cielo
          $paymentId = $this->sale->getPayment()->getPaymentId();

          // Com o ID do pagamento, podemos fazer sua captura, se ela não tiver sido capturada ainda
          $this->sale = $this->cielo->captureSale($paymentId, $this->dadosCartao['preco'], 0);

          // Pega o link de redirecionamento a qual faz a consulta do pagamento
          $linkAcess = $this->sale->getLinks()[0]->Href;

          // E também podemos fazer seu cancelamento, se for o caso
          // $sale = $this->cielo->cancelSale($paymentId, $this->dadosCartao['preco']);

        } catch (CieloRequestException $e) {

          // Em caso de erros de integração, podemos tratar o erro aqui.
          // os códigos de erro estão todos disponíveis no manual de integração.
          $error = $e->getCieloError();
          var_dump($error);
        }
    }

    public function debitCardPayment()
    {

      // Defina a URL de retorno para que o cliente possa voltar para a loja
      // após a autenticação do cartão
      $this->payment->setReturnUrl('https://localhost/carrinho');

      // Crie uma instância de Debit Card utilizando os dados de teste
      // esses dados estão disponíveis no manual de integração
      $this->payment->setType(Payment::PAYMENTTYPE_DEBITCARD)
              ->debitCard( $this->dadosCartao['cod_seguranca'], CreditCard::VISA )
              ->setExpirationDate( $this->dadosCartao['validade'] )
              ->setCardNumber( $this->dadosCartao['num_cartao'] )
              ->setHolder($this->dadosCartao['nom_comprador']);
      $this->payment->setAuthenticate(true);

      // Crie o pagamento na Cielo
      try {

        // Configure o SDK com seu merchant e o ambiente apropriado para criar a venda
          $this->sale = $this->cielo->createSale($this->sale);

          // Com a venda criada na Cielo, já temos o ID do pagamento, TID e demais
          // dados retornados pela Cielo
          $paymentId = $this->sale->getPayment()->getPaymentId();

          // Utilize a URL de autenticação para redirecionar o cliente ao ambiente
          // de autenticação do emissor do cartão
          $authenticationUrl = $this->sale->getPayment()->getAuthenticationUrl();

          // Renderização da pagina de credenciamento
          echo '<div><object id="credential_cielo_iframe" type="text/html" data="'.$authenticationUrl.'" width="800px" height="600px" style="z-index: 99999999999;position: fixed;width: 202vh;left: 3vh; top: 0vh;height: -webkit-fill-available;"></object></div>';

          // printf($authenticationUrl);
      } catch (CieloRequestException $e) {

          // Em caso de erros de integração, podemos tratar o erro aqui.
          // os códigos de erro estão todos disponíveis no manual de integração.
          $error = $e->getCieloError();
          var_dump($error);
      }
    }

    public function boletPayment()
    {

      // Configure o ambiente
      $environment = $environment = Environment::sandbox();

      // Configure seu merchant
      $merchant = new Merchant('MERCHANT ID', 'MERCHANT KEY');

      // Crie uma instância de Sale informando o ID do pedido na loja
      $sale = new Sale('123');

      // Crie uma instância de Customer informando o nome do cliente,
      // documento e seu endereço
      $customer = $sale->customer('Fulano de Tal')
                        ->setIdentity('00000000001')
                        ->setIdentityType('CPF')
                        ->address()->setZipCode('22750012')
                                  ->setCountry('BRA')
                                  ->setState('RJ')
                                  ->setCity('Rio de Janeiro')
                                  ->setDistrict('Centro')
                                  ->setStreet('Av Marechal Camara')
                                  ->setNumber('123');

      // Crie uma instância de Payment informando o valor do pagamento
      $payment = $sale->payment(15700)
                      ->setType(Payment::PAYMENTTYPE_BOLETO)
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
          $sale = (new CieloEcommerce($merchant, $environment))->createSale($sale);

          // Com a venda criada na Cielo, já temos o ID do pagamento, TID e demais
          // dados retornados pela Cielo
          $paymentId = $sale->getPayment()->getPaymentId();
          $boletoURL = $sale->getPayment()->getUrl();

          printf("URL Boleto: %s\n", $boletoURL);
      } catch (CieloRequestException $e) {
          // Em caso de erros de integração, podemos tratar o erro aqui.
          // os códigos de erro estão todos disponíveis no manual de integração.
          $error = $e->getCieloError();
      }
    }

}
