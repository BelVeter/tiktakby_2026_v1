<?php
// класс для печати договора
/**
 * Class RTF template
 * 2011 Igor Artasevych, Andrey Yaroshenko
 *
 */
class RTF_Template{
	/*****************************************************************************/
	/* variables */
	private $content;
	/* functions */
	/**
	 * RTF_Template::__construct()
	 *
	 * @param mixed $filename
	 * @return
	 */
	public function __construct($filename){
		$this->content = file_get_contents($filename);
	}//construct
	/*************************************************************************/
	/**
	 * RTF_Template::parse()
	 *
	 * @param mixed $block_name
	 * @param mixed $value
	 * @param string $start_tag
	 * @param string $end_tag
	 * @return
	 */
	public function parse($block_name, $value, $start_tag = '', $end_tag = ''){
		$this->content = str_ireplace($start_tag.$block_name.$end_tag, $value, $this->content);
	}//
	/*************************************************************************/
	/**
	 * RTF_Template::out_f()
	 *
	 * @param mixed $filename
	 * @return
	 */
	public function out_f($filename){
		file_put_contents($filename, $this->content);
	}//
	/*************************************************************************/
	/**
	 * RTF_Template::out_h()
	 *
	 * @param mixed $filename
	 * @return
	 */
	public function out_h($filename){
		ob_clean();
		header("Content-type: plaintext/rtf");
		header("Content-Disposition: attachment; filename=$filename");
		echo $this->content;
	}//
	/*************************************************************************/
	/**
	 * RTF_Template::out()
	 *
	 * @param mixed $filename
	 * @return
	 */
	public function out(){
		return $this->content;
	}//
}//class

function encode_for_rtf ($str) {
	$str = bin2hex(iconv('utf-8','windows-1251',$str));
	$str = preg_replace("/([a-zA-Z0-9]{2})/","\'$1",$str);

	return $str;
}

?>