<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Timetable extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{
        $this->load->helper('url');
		$this->load->view('timetable_index');
	}

    public function auto($days_later = 1) {
        $this->load->helper('url');
        $this->load->model('timetable_model');
        $filename = date('Ymd', time() + (86400 * $days_later));
        $this->timetable_model->download_timetable($filename);
        $this->timetable_model->parse_xml_file(sprintf('files/%s.xml', $filename));
//        $tags = array();
//        $tags['sto'] = $this->timetable_model->test_this();
//        $this->load->view('test', $tags);
    }

    public function pids($line_dir = '0', $station = '1008', $delay = 0) {
        $this->load->helper('url');
        $this->load->model('timetable_model');
        echo($this->timetable_model->pids($line_dir, urldecode($station), $delay));
    }

    public function test2() {
        $this->load->helper('url');
        $this->load->model('timetable_model');
        $this->timetable_model->get_fixed_data('station');
        $tags = array();
        $tags['sto'] = $this->timetable_model->test_this();
        $this->load->view('test', $tags);
    }

    public function test3() {
        $this->load->helper('url');
        $this->load->model('timetable_model');
    }

    public function check_station($station = '1008') {
        $this->load->helper('url');
        $this->load->model('timetable_model');
        echo($this->timetable_model->check_station(urldecode($station)));
    }

}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */
