<?php if(!defined('BASEPATH')) exit('No direct script access allowd');

class Receive extends CI_Controller {
    private $limit=10;
    private $table_name='inventory_products';
    private $sql="select shipment_id,ip.item_number,i.description
        ,date_received,quantity_received,ip.unit,ip.cost,ip.warehouse_code
                from inventory_products ip left join inventory i
                on ip.item_number=i.item_number
                where receipt_type='etc_in' 
                ";
    private $file_view='inventory/receive';
    private $primary_key='nomor_bukti';
    private $controller='receive';
    
	function __construct()
	{
		parent::__construct();
 		$this->load->helper(array('url','form','mylib_helper'));
        $this->load->library('sysvar');
		$this->load->library('template');
		$this->load->library('form_validation');
		$this->load->model('inventory_products_model');
		$this->load->model('inventory_model');
	}
	function nomor_bukti($add=false)
	{
		$key="Other Receivement Numbering";
		if($add){
		  	$this->sysvar->autonumber_inc($key);
		} else {			
			return $this->sysvar->autonumber($key,0,'!EIN~$00001');
		}
	}
	
	function set_defaults($record=NULL){
            $data=data_table($this->table_name,$record);
            $data['mode']='';
            $data['message']='';
            $data['item_number_list']=$this->inventory_model->item_list();
			$data['date_received']=date("Y-m-d H:i:s");
			if($record==NULL)$data['shipment_id']=$this->nomor_bukti();			
            return $data;
	}
	function index()
	{	
            $this->browse();
	}
	function get_posts(){
            $data=data_table_post($this->table_name);
            return $data;
	}
	function add()
	{
		 $data=$this->set_defaults();
		 $this->_set_rules();
		 if ($this->form_validation->run()=== TRUE){
			$data=$this->get_posts();
            $data['receipt_type']='etc_in';
			$data['shipment_id']=$this->nomor_bukti();
			$id=$this->inventory_products_model->save($data);
			$this->nomor_bukti(true);
            $data['message']='update success';
            $data['mode']='view';
            $this->browse();
		} else {
			$data['mode']='add';
            $this->template->display_form_input($this->file_view,$data,'');
		}
	}
	function update()
	{
	 
		 $data=$this->set_defaults();
 
		 $this->_set_rules();
 		 $id=$this->input->post($this->primary_key);
		 if ($this->form_validation->run()=== TRUE){
			$data=$this->get_posts();                    
	        unset($data['id']);
	        $this->inventory_products_model->update($id,$data);
	        $message='Update Success';
	        $this->browse();
		} else {
			$message='Error Update';
     		$this->view($id,$message);		
		}		
	}
	
	function view($id,$message=null){
		 $data['id']=$id;
		 $model=$this->inventory_products_model->get_by_id($id)->row();
		 $data=$this->set_defaults($model);
		 $data['mode']='view';
         $data['message']=$message;
         $this->template->display('inventory/receive_detail',$data);
	}
	 // validation rules
	function _set_rules(){	
		 $this->form_validation->set_rules($this->primary_key,'Nomor Bukti', 'required|trim');
	}
	
	 // date_validation callback
	function valid_date($str)
	{
	 if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$str))
	 {
		 $this->form_validation->set_message('valid_date',
		 'date format is not valid. yyyy-mm-dd');
		 return false;
	 } else {
	 	return true;
	 }
	}
	function browse($offset=0,$limit=10,$order_column='shipment_id',$order_type='asc')
	{
        $data['caption']='DAFTAR PENERIMAAN BARANG NON PURCHASE ORDER';
		$data['controller']='receive_po';		
		$data['fields_caption']=array('Nomor Bukti','Kode Barang','Nama Barang','Tanggal',
		'Qty','Unit','Cost','Gudang','');
		$data['fields']=array('shipment_id','item_number','description'
        ,'date_received','quantity_received','unit','cost','warehouse_code');
		$data['field_key']='shipment_id';
		$this->load->library('search_criteria');
		
		$faa[]=criteria("Dari","sid_date_from","easyui-datetimebox");
		$faa[]=criteria("S/d","sid_date_to","easyui-datetimebox");
		$faa[]=criteria("Nomor","sid_nomor");
		$faa[]=criteria("Supplier","sid_supplier");
		$data['criteria']=$faa;
        $this->template->display_browse2($data);            
    }
    function browse_data($offset=0,$limit=10,$nama=''){
		$sql=$this->sql;
    	$nama=$this->input->get('sid_supplier');
		$no=$this->input->get('sid_nomor');
		$d1= date( 'Y-m-d H:i:s', strtotime($this->input->get('sid_date_from')));
		$d2= date( 'Y-m-d H:i:s', strtotime($this->input->get('sid_date_to')));

		if($no!=''){
			$sql.=" and shipment_id='".$no."'";
		} else {
			$sql.=" and date_received between '$d1' and '$d2'";
			if($nama!='')$sql.=" and supplier_name like '$nama%'";	
		}
        $sql.=" limit $offset,$limit";
        echo datasource($sql);
    }
	function delete($id){
	 	$this->inventory_products_model->delete($id);
	 	$this->browse();
	}
    function detail(){
        $data['shipment_id']=isset($_GET['shipment_id'])?$_GET['shipment_id']:'';
		$data['shipment_id']=$this->nomor_bukti();
        $data['date_received']=isset($_GET['date_received'])?$_GET['date_received']:'';
        $data['supplier_number']=isset($_GET['supplier_number'])?$_GET['supplier_number']:'';
        $data['comments']=isset($_GET['comments'])?$_GET['comments']:'';
		$this->nomor_bukti(true);
        $this->template->display('inventory/receive_detail',$data);
    }
	function view_detail($nomor){
        $sql="select ip.item_number,i.description,ip.quantity_received as qty
        ,ip.unit,ip.cost,ip.id
        from inventory_products ip
        left join inventory i on i.item_number=ip.item_number
        where shipment_id='$nomor'";
        $s="
            <link rel=\"stylesheet\" type=\"text/css\" href=\"".base_url()."js/jquery-ui/themes/default/easyui.css\">
            <link rel=\"stylesheet\" type=\"text/css\" href=\"".base_url()."js/jquery-ui/themes/icon.css\">
            <link rel=\"stylesheet\" type=\"text/css\" href=\"".base_url()."js/jquery-ui/themes/demo.css\">
            <script src=\"".base_url()."js/jquery-ui/jquery.easyui.min.js\"></script>                
        ";
        echo $s." ".browse_simple($sql);
    }
    function add_item(){            
        if(isset($_GET)){
            $data['shipment_id']=$_GET['shipment_id'];
            $data['date_received']=$_GET['date_received'];
            $data['supplier_number']=$_GET['supplier_number'];
            $data['comments']=$_GET['comments'];
        } else {
            $data['shipment_id']='';
            $data['date_received']=date('YY-mm-dd');
            $data['supplier_number']='';
            $data['comments']='';                
        }
         
       $this->load->model('inventory_model');
       $data['item_lookup']=$this->inventory_model->item_list();
        $this->load->view('inventory/receive_add_item',$data);
    }   
    function save_item(){ 
        $item_no=$this->input->post('item_number');
        $data['shipment_id']=$this->input->post('shipment_id');
        $data['date_received']=$this->input->post('date_received');
        $data['supplier_number']=$this->input->post('supplier_number');
        $data['comments']=$this->input->post('comments');
        $data['item_number']=$item_no;
        $data['quantity_received']=$this->input->post('quantity_received');
        $data['unit']='pcs'; //$this->input->post('unit');
        $data['receipt_type']='etc_in';
        $rst=$this->inventory_model->get_by_id($item_no)->row();
        $data['cost']=$rst->cost;
        $this->inventory_products_model->save($data);
    }        
    function delete_item($id){
        return $this->inventory_products_model->delete_item($id);
    }        
}