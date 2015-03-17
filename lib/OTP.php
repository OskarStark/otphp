<?php

namespace OTPHP;

use Base32\Base32;

abstract class OTP implements OTPInterface
{
    /**
     * @param integer $input
     *
     * @return integer Return the OTP at the specified input
     */
    protected function generateOTP($input)
    {
        $hash = hash_hmac($this->getDigest(), $this->intToBytestring($input), $this->getDecodedSecret());
        $hmac = array();
        foreach (str_split($hash, 2) as $hex) {
            $hmac[] = hexdec($hex);
        }
        $offset = $hmac[19] & 0xf;
        $code = ($hmac[$offset+0] & 0x7F) << 24 |
            ($hmac[$offset + 1] & 0xFF) << 16 |
            ($hmac[$offset + 2] & 0xFF) << 8 |
            ($hmac[$offset + 3] & 0xFF);

        return $code % pow(10, $this->getDigits());
    }

    /**
     * @return boolean Return true is it must be included as parameter, else false
     */
    protected function issuerAsPamareter()
    {
        if ($this->getIssuer() !== null && $this->isIssuerIncludedAsParameter() === true) {
            return true;
        }

        return false;
    }

    /**
     * @param array
     */
    private function getParameters()
    {
        $options = array();
        $options['algorithm'] = $this->getDigest();
        $options['digits'] = $this->getDigits();
        $options['secret'] = $this->getSecret();
        if ($this->issuerAsPamareter()) {
            $options['issuer'] = $this->getIssuer();
        }

        return $options;
    }

    /**
     * @param array   $options
     * @param boolean $google_compatible
     */
    protected function filterOptions(array &$options, $google_compatible)
    {
        if (true === $google_compatible) {
            foreach (array("algorithm" => "sha1", "digits" => 6) as $key => $default) {
                if (isset($options[$key]) && $default === $options[$key]) {
                    unset($options[$key]);
                }
            }
        }

        ksort($options);
    }

    /**
     * @param string  $type
     * @param array   $options
     * @param boolean $google_compatible
     */
    protected function generateURI($type, array $options = array(), $google_compatible)
    {
        if ($this->getLabel() === null) {
            throw new \Exception("No label defined.");
        }
        $options = array_merge($options, $this->getParameters());
        $this->filterOptions($options, $google_compatible);

        $params = str_replace(
            array('+', '%7E'),
            array('%20', '~'),
            http_build_query($options)
        );

        return "otpauth://$type/".rawurlencode(($this->getIssuer() !== null ? $this->getIssuer().':' : '').$this->getLabel())."?$params";
    }

    /**
     * {@inheritdoc}
     */
    public function at($input)
    {
        return $this->generateOTP($input);
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    private function getDecodedSecret()
    {
        $secret = Base32::decode($this->getSecret());

        return $secret;
    }

    /**
     * @param integer $int
     *
     * @return string
     */
    private function intToBytestring($int)
    {
        $result = array();
        while ($int != 0) {
            $result[] = chr($int & 0xFF);
            $int >>= 8;
        }

        return str_pad(implode(array_reverse($result)), 8, "\000", STR_PAD_LEFT);
    }
}
