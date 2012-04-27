<?php  if( !defined('BASEPATH') ) exit('No direct script access allowed');

/**
 * email_postmark.php
 *
 * Library for working with Postmark's Send Email RESTFUL-API.
 *
 * @package   	Postmark
 * @subpackage  email_postmark
 * @author    	Jess Carlos (jess@jesscarlos.com)
 * @copyright 	Copyright (c)2012 Jess Carlos. Released Under MIT License.
 * @link    	http://jesscarlos.com
 */
 
class Email_postmark {
	
	/*
	 * API Settings
	 */
	private $key = "";
	private $server = "://api.postmarkapp.com/email";
	private $useSSL = false;
	
	/*
	 * Mail Instance
	 */
	protected $_from;
	protected $_reply_to;
	protected $_to = array();
	protected $_cc = array();
	protected $_bcc = array();
	protected $_subject;
	protected $txt_message;
	protected $html_message;
	
	/*
	 * Mail Options
	 */
	protected $_tags = array();
	
	/**
	 * Class Constructor. Allows the api settings to be rewritten
	 * on instantiation.
	 * 
	 * @access public
	 * @param string $key
	 * @param string $server
	 * @param boolean $useSSL
	 * @return void
	 */
	public function __construct($key = '', $server = '', $useSSL = FALSE)
	{
		if( $key ) $this->key = $key;
		if( $server ) $this->server = $server;
		if( $useSSL ) $this->useSSL = true;
		
		// set the prefix for the server url
		if( !$server )
		{
			$this->server = ($useSSL ? "https" : "http" ) . $this->server;
		}
	}
	
	
	/*
	 * Helper methods that gives additional options for creating an email
	 * it returns $this so you can chain multiple methods together
	 */
	
	/**
	 * Sets the from email address
	 * 
	 * @access public
	 * @param string $email
	 * @param string $name
	 * @return $this
	 */
	public function from($email, $name = '')
	{
		$this->_from = ($name ? "{$name} <{$email}>" : $email );
		return $this;
	}
	
	/**
	 * Sets a reply-to email address
	 * 
	 * @access public
	 * @param string $email
	 * @param string $name
	 * @return $this
	 */
	public function reply_to($email, $name = '')
	{
		$this->_reply_to = ($name ? "{$name} <{$email}>" : $name);
		return $this;
	}
	
	/**
	 * I've overloaded this class a little for setting to, cc and bcc email 
	 * address since the code is exactly the same. Methods should be called
	 * like: $pm->to(email, name), $pm->cc(email, name) and $pm->bcc(email, name).
	 * 
	 * The first parameter passed can be an array to allow for multiple emails to
	 * be set at once. The array structure should be:
	 * array( array('email' => [email], 'name' => [name]), array('email' => [email], 'name' => [name]) )
	 * 
	 * @access public
	 * @param string $action
	 * @param array $arguments
	 * @return $this
	 */
	public function __call($action, $arguments = array())
	{
		if( $action != "to" || $action != "cc" || $action != "bcc" || !isset($arguments[0]) )
		{
			exit("Postmark Library: Error! Method {$action} could not be found."); // dunno what i should do!?!
		}
		
		// the reference to our email holders
		$set = "_" . strtolower($action);
		
		// our settings
		$email = $arguments[0];
		$name = ( isset($arguments[1]) ? $arguments[1] : "" );
		
		if( !is_array($email) )
		{
			$this->{$set}[] = ($name ? "{$name} <{$email}>" : $email);
		}
		else 
		{
			foreach( $email as $to )
			{
				$this->{$set}[] = ($name ? "{$to['name']} <{$to['email']}>" : $to['email']);
			}
		}
		
		return $this;
	}
	
	/**
	 * Sets the subject for an email message
	 * 
	 * @access public
	 * @param string $text
	 * @return $this
	 */
	public function subject($text)
	{
		$this->_subject = $text;
		return $this;
	}
	
	/**
	 * Sets a message body. Both an html message and
	 * text message can be set
	 * 
	 * @access public
	 * @param string $body
	 * @param boolean $html
	 * @return $this
	 */
	public function message($body, $html = true)
	{
		if( $html )
		{
			$this->html_message = $body;
		}
		else
		{
			$this->txt_message = $body;
		}
		
		return $this;
	}
	
	/**
	 * Postmark allows you to set tags for your email sending for
	 * tracking. You can use this to pass your own set of tags if you 
	 * pass an array it will set multiple items at once: array(tag1,tag2,tag3...)
	 * 
	 * @access public
	 * @param mixed $name
	 * @return $this
	 */
	public function tag($name)
	{
		if( !is_array($name) )
		{
			$this->_tags[] = $name;
		}
		else
		{
			$this->_tags = $name;
		}
	}
	
	/**
	 * Sends the mail to postmark
	 * 
	 * @access public
	 * @return boolean
	 */
	public function send()
	{
		// check the required stuff
		if( !$this->_from || count($this->_to) < 1 || !$this->_subject || !$this->txt_message && !$this->html_message )
		{
			return false;
		}
		
		// build the message
		$json = array();
		
		$json['From'] = $this->_from;
		$json['To'] = implode(",", $this->_to);
		if( count($this->_cc) > 0 ) $json['Cc'] = implode(",", $this->_to);
		if( count($this->_bcc) > 0 ) $json['Bcc'] = implode(",", $this->_bcc);
		$json['Subject'] = $this->_subject;
		if( count($this->_tags) > 0 ) $json['Tag'] = implode(",", $this->_tags);
		if( $this->html_message ) $json['HtmlBody'] = $this->html_message;
		if( $this->txt_message ) $json['TextBody'] = $this->txt_message;
		if( $this->_reply_to ) $json['ReplyTo'] = $this->_reply_to;
		
		$payload = json_encode($json);
		
		// request headers
		$headers = array('Accept: application/json','Content-Type: application/json','X-Postmark-Server-Token: ' . $this->key);
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->server);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		$return = curl_exec($ch);
		
		if( curl_error($ch) !== '' )
		{
			return false;
		}
		
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if( intval($httpCode / 100 ) != 2 )
		{
			return false;
		}
		
		return true;
	}
}
 
/* End of File email_postmark.php */