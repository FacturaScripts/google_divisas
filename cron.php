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
        if ($fsvar->simple_get('google_divisas_cron')) {
            $divisa = new divisa();
            foreach ($divisa->all() as $div) {
                if ($div->coddivisa != 'EUR') {
                    $div->tasaconv_compra = $div->tasaconv = $this->convert_currency(1, 'EUR', $div->coddivisa);
                    $div->save();

                    echo '.';
                }
            }
        } else {
            echo 'Cron desactivado.';
        }
    }

    private function convert_currency($amount, $from, $to)
    {
        $url = "https://api.exchangerate-api.com/v4/latest/" . $from;
        $data = fs_file_get_contents($url);
        $json = json_decode($data, true);

        $tasa = 0;
        if (isset($json['rates'][$to])) {
            $tasa = (float) $json['rates'][$to];
        }

        return $amount * $tasa;
    }
}

new cron_google_divisas();
