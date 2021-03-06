<?php
namespace App\Http\Controllers;

use App\Category;
use App\Document;
use App\Pay;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

// Used to process plans
use Illuminate\Support\Facades\DB;
use PayPal\Api\Currency;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Api\PaymentDefinition;
use PayPal\Common\PayPalModel;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\ExecutePayment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Transaction;
use App\Order;
use App\OrderItem;
use Illuminate\Support\Facades\Input;
use Telegram\Bot\Laravel\Facades\Telegram;

class PaypalController extends Controller
{
    private $apiContext;
    private $mode;
    private $client_id;
    private $secret;


    // Create a new instance with our paypal credentials
    public function __construct()
    {
        // Detect if we are running in live mode or sandbox
        if(config('paypal.settings.mode') == 'live'){
            $this->client_id = config('paypal.live_client_id');
            $this->secret = config('paypal.live_secret');
        } else {
            $this->client_id = config('paypal.sandbox_client_id');
            $this->secret = config('paypal.sandbox_secret');
        }

        // Set the Paypal API Context/Credentials
        $this->apiContext = new ApiContext(new OAuthTokenCredential($this->client_id, $this->secret));
        $this->apiContext->setConfig(config('paypal.settings'));
    }

    public function create_plan(){

        // Create a new billing plan
        $plan = new Plan();
        $plan->setName('App Name Monthly Billing')
            ->setDescription('Monthly Subscription to the App Name')
            ->setType('infinite');

        // Set billing plan definitions
        $paymentDefinition = new PaymentDefinition();
        $paymentDefinition->setName('Regular Payments')
            ->setType('REGULAR')
            ->setFrequency('Month')
            ->setFrequencyInterval('1')
            ->setCycles('0')
            ->setAmount(new Currency(array('value' => 9, 'currency' => 'USD')));

        // Set merchant preferences
        $merchantPreferences = new MerchantPreferences();
        $merchantPreferences->setReturnUrl('https://website.dev/subscribe/paypal/return')
            ->setCancelUrl('https://website.dev/subscribe/paypal/return')
            ->setAutoBillAmount('yes')
            ->setInitialFailAmountAction('CONTINUE')
            ->setMaxFailAttempts('0');

        $plan->setPaymentDefinitions(array($paymentDefinition));
        $plan->setMerchantPreferences($merchantPreferences);

        //create the plan
        try {
            $createdPlan = $plan->create($this->apiContext);

            try {
                $patch = new Patch();
                $value = new PayPalModel('{"state":"ACTIVE"}');
                $patch->setOp('replace')
                    ->setPath('/')
                    ->setValue($value);
                $patchRequest = new PatchRequest();
                $patchRequest->addPatch($patch);
                $createdPlan->update($patchRequest, $this->apiContext);
                $plan = Plan::get($createdPlan->getId(), $this->apiContext);

                // Output plan id
                echo 'Plan ID:' . $plan->getId();
            } catch (PayPal\Exception\PayPalConnectionException $ex) {
                echo $ex->getCode();
                echo $ex->getData();
                die($ex);
            } catch (Exception $ex) {
                die($ex);
            }
        } catch (PayPal\Exception\PayPalConnectionException $ex) {
            echo $ex->getCode();
            echo $ex->getData();
            die($ex);
        } catch (Exception $ex) {
            die($ex);
        }

    }

    public function postPaymentDoc(Document $document)
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');
        $items = array();
        $subtotal = 0;
        $currency = 'EUR';



            $item = new Item();
            $item->setName($document->name)
                ->setCurrency($currency)
                ->setDescription($document->description)
                ->setQuantity(1)
                ->setPrice($document->price);
            $items[] = $item;


        $item_list = new ItemList();
        $item_list->setItems($items);
        $details = new Details();
        $details->setSubtotal($document->price);

        $amount = new Amount();
        $amount->setCurrency($currency)
            ->setTotal($document->price)
            ->setDetails($details);
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($item_list)
            ->setDescription($document->name);
        $redirect_urls = new RedirectUrls();
        $redirect_urls->setReturnUrl(\URL::route('payment.status',$document))
            ->setCancelUrl(\URL::route('payment.status',$document));
        $payment = new Payment();
        $payment->setIntent('Sale')
            ->setPayer($payer)
            ->setRedirectUrls($redirect_urls)
            ->setTransactions(array($transaction));
        try {
            $payment->create($this->apiContext);
        } catch (PayPal\Exception\PPConnectionException $ex) {
            if (Config::get('app.debug')) {
                echo "Exception: " . $ex->getMessage() . PHP_EOL;
                $err_data = json_decode($ex->getData(), true);
                exit;
            } else {
                die('Ups! Algo salió mal');
            }
        }
        foreach($payment->getLinks() as $link) {
            if($link->getRel() == 'approval_url') {
                $redirect_url = $link->getHref();
                break;
            }
        }
        // add payment ID to session
        \Session::put('paypal_payment_id', $payment->getId());
        if(isset($redirect_url)) {
            // redirect to paypal
            return \Redirect::away($redirect_url);
        }
        return \Redirect::route('cart-show')
            ->with('error', 'Ups! Error desconocido.');
    }
    public function postPaymentCat($categori, $total)
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');
        $items = array();
        $subtotal = 0;
        $currency = 'EUR';
        $category = Category::find($categori);


        $item = new Item();
        $item->setName($category->name)
            ->setCurrency($currency)
            ->setDescription($category->description)
            ->setQuantity(1)
            ->setPrice($total);
        $items[] = $item;


        $item_list = new ItemList();
        $item_list->setItems($items);
        $details = new Details();
        $details->setSubtotal($total);

        $amount = new Amount();
        $amount->setCurrency($currency)
            ->setTotal($total)
            ->setDetails($details);
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($item_list)
            ->setDescription('Compra de los derechos sobre los documentos de una categoría');
        $redirect_urls = new RedirectUrls();
        $redirect_urls->setReturnUrl(\URL::route('payment.status2',[$category,$total]))
            ->setCancelUrl(\URL::route('payment.status2',[$category,$total]));
        $payment = new Payment();
        $payment->setIntent('Sale')
            ->setPayer($payer)
            ->setRedirectUrls($redirect_urls)
            ->setTransactions(array($transaction));
        try {
            $payment->create($this->apiContext);
        } catch (PayPal\Exception\PPConnectionException $ex) {
            if (Config::get('app.debug')) {
                echo "Exception: " . $ex->getMessage() . PHP_EOL;
                $err_data = json_decode($ex->getData(), true);
                exit;
            } else {
                die('Ups! Algo salió mal');
            }
        }
        foreach($payment->getLinks() as $link) {
            if($link->getRel() == 'approval_url') {
                $redirect_url = $link->getHref();
                break;
            }
        }
        // add payment ID to session
        \Session::put('paypal_payment_id', $payment->getId());
        if(isset($redirect_url)) {
            // redirect to paypal
            return \Redirect::away($redirect_url);
        }
        return \Redirect::route('cart-show')
            ->with('error', 'Ups! Error desconocido.');
    }



    public function getPaymentStatus(Document $document)
    {

        $payment_id = \Session::get('paypal_payment_id');

        \Session::forget('paypal_payment_id');
        $payerId = Input::get('PayerID');
        $token = Input::get('token');

        if (empty($payerId) || empty($token)) {
            return \Redirect::route('home')
                ->with('flash', 'Hubo un problema al intentar pagar con Paypal');
        }


        $payment = Payment::get($payment_id, $this->apiContext);

        $execution = new PaymentExecution();
        $execution->setPayerId(Input::get('PayerID'));

        $result = $payment->execute($execution, $this->apiContext);

        if ($result->getState() == 'approved') { // payment made

            $this->saveOrderDoc($document);
            $documentele = Document::find($document->id);
            $balance =  DB::select( DB::raw("select round((SUM(amount)/100),2) * 30 as doc, round((SUM(amount)/100),2) * 70 as users , round(SUM(amount),2) as total from pays"));

            $text = 'El usuario <b>'.auth()->user()->name .' '. auth()->user()->lastname .'</b> ha comprado el documento <b>'.$documentele->name.'</b> por un total: '.$document->price. '€ '
                . '<b>'.PHP_EOL.'Saldo Total : '.$balance[0]->total.' € </b>'.PHP_EOL.'<b>Saldo de los Usuarios : </b>'.$balance[0]->users.' €'.PHP_EOL.'<b>Saldo de DocCloud : </b>'.$balance[0]->doc.' €';

            Telegram::sendMessage([
                'chat_id' => env('TELEGRAM_CHANNEL_ID', '-1001208921290'),
                'parse_mode' => 'HTML',
                'text' => $text
            ]);

            return \Redirect::route('home')
                ->with('flash', 'Compra realizada de forma correcta');
        }
        return \Redirect::route('home')
            ->with('flash', 'La compra fue cancelada');
    }

    public function getPaymentStatus2(Category $category,$total)
    {


        $payment_id = \Session::get('paypal_payment_id');

        \Session::forget('paypal_payment_id');
        $payerId = Input::get('PayerID');
        $token = Input::get('token');

        if (empty($payerId) || empty($token)) {
            return \Redirect::route('home')
                ->with('flash', 'Hubo un problema al intentar pagar con Paypal');
        }


        $payment = Payment::get($payment_id, $this->apiContext);

        $execution = new PaymentExecution();
        $execution->setPayerId(Input::get('PayerID'));

        $result = $payment->execute($execution, $this->apiContext);

        if ($result->getState() == 'approved') { // payment made

            $docus = session()->get('documentstopay');

            $price = round($total / count($docus),2);
            foreach ($docus as $doc){
                $this->saveOrderDocCat($doc,$price);
            }


            $documentele = Category::find($category->id);
            $balance =  DB::select( DB::raw("select round((SUM(amount)/100),2) * 30 as doc, round((SUM(amount)/100),2) * 70 as users , round(SUM(amount),2) as total from pays"));

            $text = 'El usuario <b>'.auth()->user()->name .' '. auth()->user()->lastname .'</b> ha comprado la categoria <b>'.$documentele->name.'</b> con acceso a '.count($docus).' documentos por un total: '.$total. '€ '
                . '<b>'.PHP_EOL.'Saldo Total : '.$balance[0]->total.' € </b>'.PHP_EOL.'<b>Saldo de los Usuarios : </b>'.$balance[0]->users.' €'.PHP_EOL.'<b>Saldo de DocCloud : </b>'.$balance[0]->doc.' €';

            Telegram::sendMessage([
                'chat_id' => env('TELEGRAM_CHANNEL_ID', '-1001208921290'),
                'parse_mode' => 'HTML',
                'text' => $text
            ]);

            return \Redirect::route('home')
                ->with('flash', 'Compra realizada de forma correcta');
        }
        return \Redirect::route('home')
            ->with('flash', 'La compra fue cancelada');
    }

    public function saveOrderDoc($document)
    {
        $payment = new Pay();
        $payment->user_id = auth()->user()->id;
        $payment->document_id = $document->id;
        $payment->amount = $document->price;
        $payment->save();
    }

    public function saveOrderDocCat($document,$price)
    {
        $payment = new Pay();
        $payment->user_id = auth()->user()->id;
        $payment->document_id = $document->id;
        $payment->amount = $price;
        $payment->save();
    }



}
