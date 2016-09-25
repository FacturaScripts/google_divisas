<?php

/*
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2016, Carlos García Gómez. All Rights Reserved. 
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('divisa.php');

/**
 * Description of cron_google_divisas
 *
 * @author carlos
 */
class cron_google_divisas
{
   public function __construct()
   {
      $fsvar = new fs_var();
      if( $fsvar->simple_get('google_divisas_cron') )
      {
         $divisa = new divisa();
         foreach($divisa->all() as $div)
         {
            if($div->coddivisa != 'EUR')
            {
               $div->tasaconv_compra = $div->tasaconv = $this->convert_currency(1, 'EUR', $div->coddivisa);
               $div->save();
               
               echo '.';
            }
         }
      }
      else
      {
         echo 'Cron desactivado.';
      }
   }
   
   private function convert_currency($amount, $from, $to)
   {
      $url = "http://www.google.com/finance/converter?a=".$amount."&from=".$from."&to=".$to;
      $data = $this->curl_get_contents($url);
      $converted = array();
      preg_match("/<span class=bld>(.*)<\/span>/",$data, $converted);
      
      if( count($converted) > 0 )
      {
         return floatval($converted[1]);
      }
      else
      {
         return 1;
      }
   }
   
   /**
    * Descarga el contenido con curl o file_get_contents
    * @param type $url
    * @param type $timeout
    * @return type
    */
   private function curl_get_contents($url)
   {
      if( function_exists('curl_init') )
      {
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
         curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
         curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
         $data = curl_exec($ch);
         $info = curl_getinfo($ch);
         
         if($info['http_code'] == 301 OR $info['http_code'] == 302)
         {
            $redirs = 0;
            return $this->curl_redirect_exec($ch, $redirs);
         }
         else
         {
            curl_close($ch);
            return $data;
         }
      }
      else
         return file_get_contents($url);
   }
   
   /**
    * Función alternativa para cuando el followlocation falla.
    * @param type $ch
    * @param type $redirects
    * @param type $curlopt_header
    * @return type
    */
   private function curl_redirect_exec($ch, &$redirects, $curlopt_header = false)
   {
      curl_setopt($ch, CURLOPT_HEADER, true);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $data = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      
      if($http_code == 301 || $http_code == 302)
      {
         list($header) = explode("\r\n\r\n", $data, 2);
         $matches = array();
         preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
         $url = trim(str_replace($matches[1], "", $matches[0]));
         $url_parsed = parse_url($url);
         if( isset($url_parsed) )
         {
            curl_setopt($ch, CURLOPT_URL, $url);
            $redirects++;
            return $this->curl_redirect_exec($ch, $redirects, $curlopt_header);
         }
      }
      
      if($curlopt_header)
      {
         curl_close($ch);
         return $data;
      }
      else
      {
         list(, $body) = explode("\r\n\r\n", $data, 2);
         curl_close($ch);
         return $body;
      }
   }
}

new cron_google_divisas();