<?php if(!defined('BASEPATH')) exit('No direct script access allowd');

class Sales_retur extends CI_Controller {
    private $limit=10;
    private $sql="select i.invoice_number,i.invoice_date,i.amount,i.posted,i.your_order__, 
            i.sold_to_customer,c.company,c.city,i.warehouse_code
            from invoice i
            left join customers c on c.customer_number=i.sold_to_customer
            where  invoice_type='R' ";
    private $controller='sales_retur';
    private $primary_key='invoice_number';
    private $file_view='sales/retur';
    private $table_name='invoice';
	function __construct()
	{
		parent::__construct();
		if(!$this->access->is_login())redirect(base_url());
 		$this->load->helper(array('url','form','browse_select','mylib_helper'));
        $this->load->library('sysvar');
        $this->load->library('javascript');
        $this->load->library('template');
		$this->load->library('form_validation');
		$this->load->model('invoice_model');
		$this->load->model('invoice_lineitems_model');
		$this->load->model('customer_model');
        $this->load->model('inventory_model');
        $this->load->model('type_of_payment_model');
		$this->load->model('salesman_model');
		$this->load->model('syslog_model');
	}


	function index()
	{          
		if (!allow_mod2('_30090'))  exit;
        $this->browse();
	}
	
    function browse($offset=0,$limit=50,$order_column='sales_order_number',$order_type='asc'){
		$data['controller']=$this->controller;
		$data['fields_caption']=array('Nomor Bukti','Tanggal','Jumlah','Posted','Faktur','Kode Cust','Nama Customer',
			'Salesman','Kota','Gudang');
		$data['fields']=array('invoice_number','invoice_date','amount', 'posted','your_order__',
            'sold_to_customer','company','salesman','city','warehouse_code');
		$data['field_key']='invoice_number';
		$data['caption']='DAFTAR RETUR PENJUALAN';
		$data['posting_visible']=true;

		$this->load->library('search_criteria');
		
		$faa[]=criteria("Dari","sid_date_from","easyui-datetimebox");
		$faa[]=criteria("S/d","sid_date_to","easyui-datetimebox");
		$faa[]=criteria("Nomor","sid_number");
		$faa[]=criteria("Pelanggan","sid_cust");
		$faa[]=criteria("Salesman","sid_salesman");
		$faa[]=criteria("Posted","sid_posted");

		$data['criteria']=$faa;
        $this->template->display_browse2($data);            
    }
    function browse_data($offset=0,$limit=100,$nama=''){
    	$nama=$this->input->get('sid_cust');
		$no=$this->input->get('sid_number');
		$d1= date( 'Y-m-d H:i:s', strtotime($this->input->get('sid_date_from')));
		$d2= date( 'Y-m-d H:i:s', strtotime($this->input->get('sid_date_to')));
        $sql=$this->sql;
		if($no!=''){
			$sql.=" and invoice_number='".$no."'";
		} else {
			$sql.=" and invoice_date between '$d1' and '$d2'";
			if($nama!='')$sql.=" and company like '$nama%'";	
			if($this->input->get('sid_salesman')!='')$sql.=" and salesman like '".$this->input->get('salesman')."%'";
			if($this->input->get('sid_posted')!=''){
				if($this->input->get('sid_posted')=='1'){
					$sql.=" and posted=true";
				} else {
					$sql.=" and (posted=false or posted is null)";				
				}
			}
		}
		if(lock_report_salesman())$sql.=" and i.salesman='".current_salesman()."'";
        $sql.=" limit $offset,$limit";
        echo datasource($sql);
    }	 
		
	function add()
	{
		if (!allow_mod2('_30091'))  exit;
		 $data=$this->set_defaults();
		 $this->_set_rules();
		 if ($this->form_validation->run()=== TRUE){
			$data['invoice_number']=$this->nomor_bukti();
			$data['invoice_type']='I';
			$this->invoice_model->save($data);
			$this->nomor_bukti(true);
			$id=$data['invoice_number'];
            $this->view($id,'Finish');
   		} else {
			$data['mode']='add';
			$data['message']='';
            $data['sold_to_customer']=$this->input->post('sold_to_customer');
            $data['amount']=$this->input->post('amount');
			$data['comments']='';
			$data['your_order__']='';
			$this->template->display_form_input($this->file_view,$data,'');			
		}
	}

	 // validation rules
	function _set_rules(){	
		 $this->form_validation->set_rules('invoice_number','Nomor Faktur', 'required|trim');
		 $this->form_validation->set_rules('invoice_date','Tanggal','callback_valid_date');
		 $this->form_validation->set_rules('sold_to_customer','Pelanggan', 'required|trim');
	}
	 // date_validation callback
	function valid_date($str){
		 if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$str)) {
			 $this->form_validation->set_message('valid_date','Format tanggal salah, seharusnya yyyy-mm-dd');
			 return false;
		 } else {
		 	return true;
		 }
	}
	function set_defaults($record=NULL){
        //$data['library_src'] = $this->jquery->script();
        //$data['script_head'] = $this->jquery->_compile();
		$data['mode']='';
		$data['message']='';
        $data['warehouse_code']=$this->access->cid;
		$data['invoice_date']= date("Y-m-d");
		if($record==NULL)$data['invoice_number']=$this->nomor_bukti();
        $data['invoice_type']='R';
		$data['customer_info']='';
		$data['posted']=0;
		return $data;
	}


	function nomor_bukti($add=false)
	{
		$key="Invoice Retur Numbering";
		if($add){
		  	$this->sysvar->autonumber_inc($key);
		} else {			
			$no=$this->sysvar->autonumber($key,0,'!JRE~$00001');
			for($i=0;$i<100;$i++){			
				$no=$this->sysvar->autonumber($key,0,'!JRE~$00001');
				$rst=$this->invoice_model->get_by_id($no)->row();
				if($rst){
				  	$this->sysvar->autonumber_inc($key);
				} else {
					break;					
				}
			}
			return $no;
		}
	}
	
	function save()
	{
		
		$mode=$this->input->post('mode');
		if($mode=="add"){
	        $id=$this->nomor_bukti();
		} else {
			$id=$this->input->post('invoice_number');			
		}
		$data['invoice_number']=$id;
		$data['invoice_date']=$this->input->post('invoice_date');
		$data['your_order__']=$this->input->post('your_order__');
		$data['invoice_type']='R';
		$data['sold_to_customer']=$this->input->post('sold_to_customer');
		$data['due_date']=$this->input->post('invoice_date');
		$data['comments']=$this->input->post('comments');
		$data['warehouse_code']="";
		if($mode=="add"){
			$ok=$this->invoice_model->save($data);
			$this->syslog_model->add($id,"sales_retur","add");

		} else {
			$ok=$this->invoice_model->update($id,$data);			
			$this->syslog_model->add($id,"sales_retur","edit");

		}
		if ($ok){
			if($mode=="add")$this->nomor_bukti(true);
			echo json_encode(array('success'=>true,'invoice_number'=>$id));
		} else {
			echo json_encode(array('msg'=>'Some errors occured.'));
		}
	}
	function delete($id){
		if (!allow_mod2('_30093',true))  exit;
		$id=urldecode($id);
		$this->load->model("periode_model");
		$q=$this->invoice_model->get_by_id($id);
		if($this->periode_model->closed($q->row()->invoice_date)){
			$message="Periode sudah ditutup tidak bisa dihapus !";
			$this->syslog_model->add($id,"sales_retur","delete");

			$this->view($id,$message);
			return false;
		}
		$this->load->model('jurnal_model');
		 
		if($this->jurnal_model->get_by_gl_id($id)->row()) {
			$message="Sudah dijurnal tidak bisa dihapus !";
			$this->view($id,$message);
			return false;
		}

		$this->load->model('invoice_model');
	 	$this->invoice_model->delete($id);
	}
	function view($id,$message=null){
		if (!allow_mod2('_30090'))  exit;
		$id=urldecode($id);
		 $model=$this->invoice_model->get_by_id($id)->row();
		 $data=$this->set_defaults($model);
		 $data['mode']='view';
         $data['message']=$message;
		 $data['sold_to_customer']=$model->sold_to_customer;
         $data['customer_info']=$this->customer_model->info($data['sold_to_customer']);
		 $data['comments']=$model->comments;
		 $data['invoice_number']=$id;
		 $data['your_order__']=$model->your_order__;
		 $data['posted']=$model->posted;
		 
		 $this->invoice_model->recalc($id);
         $this->template->display('sales/retur',$data);                 
	}
	function unposting($nomor) {
		if (!allow_mod2('_30095'))  exit;
		$nomor=urldecode($nomor);
		$message=$this->invoice_model->unposting($nomor);		
		$this->view($nomor);
	}
	function posting($nomor)
	{
		if (!allow_mod2('_30095'))  exit;
		$nomor=urldecode($nomor);
		$message=$this->invoice_model->posting_retur($nomor);
		$this->view($nomor);
	}		
	function posting_all() {
		$d1= date( 'Y-m-d H:i:s', strtotime($this->input->get('sid_date_from')));
		$d2= date( 'Y-m-d H:i:s', strtotime($this->input->get('sid_date_to')));
		$sql="select distinct invoice_number from invoice"; 
		$sql.=" where invoice_type in ('R') and (posted is null or posted=false) and invoice_date between '$d1' and '$d2'";
		
		if($q=$this->db->query($sql)){
			foreach($q->result() as $r){
				echo "<p>Posting..
				<a href=".base_url()."index.php/sales_retur/view/".$r->invoice_number."
				class='info_link'>".$r->invoice_number."</a> : ";
				$message=$this->invoice_model->posting($r->invoice_number);
				if($message!=''){
					echo ': '.$message;
				}
				echo "</p>";
			}
		}
		echo "<p>Finish.</p>";
	}			
    function print_bukti($nomor){
		if (!allow_mod2('_30094'))  exit;
		$nomor=urldecode($nomor);
        $invoice=$this->invoice_model->get_by_id($nomor)->row();
		 
		$saldo=$this->invoice_model->recalc($nomor);
		$data['invoice_number']=$invoice->invoice_number;
		$data['invoice_date']=$invoice->invoice_date;
		$data['sold_to_customer']=$invoice->sold_to_customer;
		$data['comments']=$invoice->comments;
		$data['sales_order_number']=$invoice->sales_order_number;
		$data['due_date']=$invoice->due_date;
		$data['amount']=$invoice->amount;
		$data['sub_total']=$invoice->subtotal;
		$data['discount']=$invoice->discount;
		$data['disc_amount']=$invoice->subtotal*$invoice->discount;
		$data['freight']=$invoice->freight;
		$data['others']=$invoice->other;
		$data['tax']=$invoice->sales_tax_percent;
		$data['tax_amount']=$invoice->sales_tax_percent*($data['sub_total']-$data['disc_amount']);
        $data['content']=load_view('sales/rpt/print_retur',$data);    	
        $this->load->view('pdf_print',$data);    	
    }
	
}

?>