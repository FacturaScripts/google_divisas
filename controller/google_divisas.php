<?php

/**
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2016-2017, Carlos García Gómez. All Rights Reserved. 
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
 * Description of google_divisas
 *
 * @author carlos
 */
class google_divisas extends fs_controller
{
   public $setup_cron;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Google Divisas', 'admin', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->share_extensions();
      
      $fsvar = new fs_var();
      $this->setup_cron = $fsvar->simple_get('google_divisas_cron');
      
      if( isset($_GET['consultar']) )
      {
         $divisa = new divisa();
         foreach($divisa->all() as $div)
         {
            if($div->coddivisa != 'EUR')
            {
               $div->tasaconv_compra = $div->tasaconv = $this->convert_currency(1, 'EUR', $div->coddivisa);
               $div->save();
            }
         }
         
         $this->new_message('Tasas de conversión actualizadas. '
                 . '<a href="index.php?page=admin_divisas" target="_parent">Recarga la página</a>.');
      }
      else if( isset($_GET['cron']) )
      {
         $this->setup_cron = '1';
         $fsvar->simple_save('google_divisas_cron', $this->setup_cron);
         $this->new_message('Cron activado.');
      }
      else if( isset($_GET['nocron']) )
      {
         $this->setup_cron = FALSE;
         $fsvar->simple_delete('google_divisas_cron');
         $this->new_message('Cron desactivado.');
      }
   }
   
   private function share_extensions()
   {
      $fsext = new fs_extension();
      $fsext->name = 'tab_divisas';
      $fsext->from = __CLASS__;
      $fsext->to = 'admin_divisas';
      $fsext->type = 'modal';
      $fsext->text = '<i class="fa fa-globe"></i><span class="hidden-xs">&nbsp; Google</span>';
      $fsext->save();
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
         if( defined('FS_PROXY_TYPE') )
         {
            curl_setopt($ch, CURLOPT_PROXYTYPE, FS_PROXY_TYPE);
            curl_setopt($ch, CURLOPT_PROXY, FS_PROXY_HOST);
            curl_setopt($ch, CURLOPT_PROXYPORT, FS_PROXY_PORT);
         }
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
      if( defined('FS_PROXY_TYPE') )
      {
         curl_setopt($ch, CURLOPT_PROXYTYPE, FS_PROXY_TYPE);
         curl_setopt($ch, CURLOPT_PROXY, FS_PROXY_HOST);
         curl_setopt($ch, CURLOPT_PROXYPORT, FS_PROXY_PORT);
      }
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
