<?php
trait AESUtility
{
	public $AESsalt = null;
	public $AESkey = null;
	private $AESalgorithmKeyLength = array(MCRYPT_RIJNDAEL_256 => 32);
	public $AESalgorithm = MCRYPT_RIJNDAEL_256;
	public function encrypt($val)
	{
		return rtrim(
					base64_encode(
						\mcrypt_encrypt(
							$this->AESalgorithm,
							substr($this->AESkey, 0, $this->AESalgorithmKeyLength[$this->AESalgorithm]), $val,
							MCRYPT_MODE_ECB,
							mcrypt_create_iv(
								mcrypt_get_iv_size(
									$this->AESalgorithm,
									MCRYPT_MODE_ECB
								),
								MCRYPT_RAND
							)
						)
					),"\0"
				);
	}
	public function decrypt($hash)
	{
		return rtrim(
			\mcrypt_decrypt(
				$this->AESalgorithm,
				substr($this->AESkey,0, $this->AESalgorithmKeyLength[$this->AESalgorithm]),
				base64_decode($hash),
				MCRYPT_MODE_ECB,
				mcrypt_create_iv(
					mcrypt_get_iv_size(
						$this->AESalgorithm,
						MCRYPT_MODE_ECB
					),
					MCRYPT_RAND
				)
			),"\0"
		);
	}
	public function encryptSalted($val)
	{
		return rtrim(
					base64_encode(
						\mcrypt_encrypt(
							$this->AESalgorithm,
							substr(sha1($this->AESkey.$this->AESsalt), -$this->AESalgorithmKeyLength[$this->AESalgorithm]), $val,
							MCRYPT_MODE_ECB,
							mcrypt_create_iv(
								mcrypt_get_iv_size(
									$this->AESalgorithm,
									MCRYPT_MODE_ECB
								),
								MCRYPT_RAND
							)
						)
					),"\0"
				);
	}
	public function decryptSalted($hash)
	{
		return rtrim(
			\mcrypt_decrypt(
				$this->AESalgorithm,
				substr(sha1($this->AESkey.$this->AESsalt), -$this->AESalgorithmKeyLength[$this->AESalgorithm]),
				base64_decode($hash),
				MCRYPT_MODE_ECB,
				mcrypt_create_iv(
					mcrypt_get_iv_size(
						$this->AESalgorithm,
						MCRYPT_MODE_ECB
					),
					MCRYPT_RAND
				)
			),"\0"
		);
	}
}
