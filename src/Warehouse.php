<?php
class Warehouse extends \atk4\ui\App
{

    /**
     * Currently logged in user
     * @var User
     */
    public $user;
    public $company;

    public $vat_rates = ['21.0', '10.0', '0'];
    public $currencies = ['USD','GBP','EUR'];


    function __construct($auth = true) {
        if (is_dir('public')) {
            $this->cdn['atk'] = 'public';
        }
        parent::__construct('Warehouse App v0.5');


        // Connect to database (Heroku or Local)
        if (isset($_ENV['CLEARDB_DATABASE_URL'])) {
            $this->db = \atk4\data\Persistence::connect($_ENV['CLEARDB_DATABASE_URL']);
        } else {
            $this->db = \atk4\data\Persistence::connect('mysql:host=127.0.0.1;dbname=warehouse', 'root', 'root');
        }
        $this->db->app = $this;


        $this->user = new User($this->db);
        $this->company = new Company($this->db);
        session_start();

        if (!$auth) {
            $this->initLayout('Centered');
            return;
        }

        if (isset($_SESSION['user_id'])) {
            $this->user->tryLoad($_SESSION['user_id']);
        }

        if(!$this->user->loaded()) {
            $this->initLayout('Centered');
            $this->layout->add(['Message', 'Login Required', 'error']);
            $this->layout->add(['Button', 'Login', 'primary'])->link('index.php');
            exit;
        }

        $this->company = $this->user->ref('company_id');

        $this->initLayout('Admin');

        $this->layout->leftMenu->addItem(['Home', 'icon'=>'home'], ['dashboard']);
        $this->layout->leftMenu->addItem(['Partners', 'icon'=>'users'], ['partners']);

        $mr = $this->layout->menu->addMenuRight();
        $mr ->addItem([$this->user['email'], 'icon'=>'user']);
        $mr ->addItem(['Logout', 'icon'=>'sign out'], ['logout']);

        $this->layout->leftMenu->addItem(['Categories', 'icon'=>'folder open'], ['category']);

        // Section of our application dealing with current stock flow and history
        $this->layout->leftMenu->addItem(['Articles', 'icon'=>'barcode'], ['stock']);

        // manage.php contains a CRUD which will work with most basic Models

        // production uses a custom page, we want some freedom, so, separate page
        $this->layout->menuLeft->addItem(['Production', 'icon'=>'cogs'], ['production']);

        // Stock model changes amount of stocked articles, but can be one of several types.
        // Inventory and write-off can be created by user directly, but Effect is created
        // automatically in response to actions on invoices.
        //$m->addItem(['Inventory', 'label'=>'coming soon'], ['stock', 'type'=>'inventory']);
        //$m->addItem(['Write-off', 'label'=>'coming soon'], ['stock', 'type'=>'write-off']);

        //$m->addItem(['Effect', 'label'=>'coming soon'],    ['effect']);

        // Supply section deals with invoices and payments, but will also affect stock
        $m = $this->layout->leftMenu->addGroup(['Supply', 'icon'=>'shipping']);
        //$m->addItem(['At a glance', 'label'=>'coming soon'], ['supply']);

        // Suppliers is a easy and manageable entity
        $m->addItem('Suppliers', ['manage', 'model'=>'Supplier']);

        // Invoices and Credit notes will create Effect documents
        // automatically when changing status. Otherwise, they are same as prepaid bills
        $m->addItem('Invoices', ['docs',    'type'=>'purchase']);

        // Invoice can be converted into credit note
        //$m->addItem(['Credit Notes', 'label'=>'coming soon'], ['docs','type'=>'credit-note', 'dir'=>'supply']);

        //$m->addItem(['Reports', 'label'=>'coming soon'], ['supplier-reports']);

        $m = $this->layout->leftMenu->addGroup(['Sales', 'icon'=>'shop']);
        //$m->addItem(['At a glance', 'label'=>'coming soon'], ['sales']);

        $m->addItem('Clients', ['manage', 'model'=>'Client']);

        // Prepaid bill does not have effect on stock but can be converted into invoice
        //$m->addItem(['Prepaid Bills', 'label'=>'coming soon'], ['docs', 'type'=>'prepaid-bill', 'dir'=>'sale']);

        // Invoices
        $m->addItem('Invoices', ['docs',    'type'=>'sale']);
        //$m->addItem(['Credit Notes', 'label'=>'coming soon'], ['docs','type'=>'credit-note', 'dir'=>'sale']);


        $a = new Article($this->db);
        $a->addCondition('stock', '<', 0);
        $c = $a->action('count')->getOne();
        if ($c>0) {
            $this->layout->menuRight->addItem(null, ['stock', 'negative'=>true])->add(['Label', 'There are '.$c.' articles with negative stock', 'red']);
        }

    }
}
