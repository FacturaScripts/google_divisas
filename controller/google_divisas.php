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
 * Description of google_divisas
 *
 * @author carlos
 */
class google_divisas extends fs_controller
{
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Google Divisas', 'admin', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->share_extensions();
      
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
      $url = "http://www.google.com/finance/converter?a=$amount&from=$from&to=$to";
      $data = file_get_contents($url);
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
}
