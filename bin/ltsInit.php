#!/usr/bin/php
<?php
function __autoload($cn) { include_once './'.$cn.'.php';  } // AUTOLOAD

// Country list
$countries = array ("1:Canada:CA", "2:United States:US", "3:Afghanistan:AF", "4:Albania:AL", "5:Algeria:DZ",
                    "6:American Samoa:AS", "7:Andorra:AD", "8:Angola:AO", "9:Anguilla:AI","10:Antarctica:AQ",
                    "11:Antigua and Barbuda:AG", "12:Argentina:AR", "13:Armenia:AM", "14:Aruba:AW", "15:Australia:AU",
                    "16:Austria:AT", "17:Azerbaijan:AZ", "18:Bahamas:BS", "19:Bahrain:BH", "20:Bangladesh:BD",
                    "21:Barbados:BB", "22:Belarus:BY", "23:Belgium:BE", "24:Belize:BZ", "25:Benin:BJ",
                    "26:Bermuda:BM", "27:Bhutan:BT", "28:Bolivia:BO", "29:Bosnia and Herzegowina:BA", "30:Botswana:BW",
                    "31:Bouvet Island:BV", "32:Brazil:BR", "33:British Indian Ocean Ter.:IO", "34:Brunei Darussalam:BN", 
                    "35:Bulgaria:BG", "36:Burkina Faso:BF", "37:Burundi:BI", "38:Cambodia:KH", "39:Cameroon:CM",
                    "40:Cape Verde:CV", "41:Cayman Islands:KY", "42:Central African Republic:CF", "43:Chad:TD", "44:Chile:CL",
                    "45:China:CN", "46:Christmas Island:CX", "47:Cocos (Keeling) Islands:CC", "48:Colombia:CO", "49:Comoros:KM",
                    "50:Congo:CG", "51:Cook Islands:CK", "52:Costa Rica:CR", "53:Cote D'Ivoire:CI", "54:Croatia (Hrvatska):HR",
                    "55:Cuba:CU", "56:Cyprus:CY", "57:Czech Republic:CZ", "58:Denmark:DK", "59:Djibouti:DJ", "60:Dominica:DM",
                    "61:Dominican Republic:DO", "62:East Timor:TP", "63:Ecuador:EC", "64:Egypt:EG", "65:El Salvador:SV", 
                    "66:Equatorial Guinea:GQ", "67:Eritrea:ER", "68:Estonia:EE", "69:Ethiopia:ET", "70:Falkland Islands (Malvinas):FK",
                    "71:Faroe Islands:FO", "72:Fiji:FJ", "73:Finland:FI", "74:France:FR", "75:France, Metropolitan:FX", 
                    "76:French Guiana:GF", "77:French Polynesia:PF", "78:French Southern Territories:TF", "79:Gabon:GA", 
                    "80:Gambia:GM", "81:Georgia:GE", "82:Germany:DE", "83:Ghana:GH", "84:Gibraltar:GI", "85:Greece:GR",
                    "86:Greenland:GL", "87:Grenada:GD", "88:Guadeloupe:GP", "89:Guam:GU", "90:Guatemala:GT", "91:GN Guinea:GN",
                    "92:Guinea-Bissau:GW", "93:Guyana:GY", "94:Haiti:HT","95:Heard and Mc Donald Islands:HM", "96:Honduras:HN",
                    "97:Hong Kong:HK", "98:Hungary:HU", "99:Iceland:IS", "100:India:IN", "101:Indonesia:ID", 
                    "102:Iran (Islamic Republic of):IR", "103:Iraq:IQ", "104:Ireland:IE", "105:Israel:IL", "106:Italy:IT", 
                    "107:Jamaica:JM", "108:Japan:JP","109:Jordan:JO", "110:Kazakhstan:KZ", "111:Kenya:KE", "112:Kiribati:KI",
                    "113:Korea, Dem. People's Rep. of:KP", "114:Korea, Rep. of:KR", "115:Kuwait:KW", "116:Kyrgyzstan:KG",
                    "117:Lao People's Dem. Rep.:LA", "118:Latvia:LV", "119:Lebanon:LB", "120:Lesotho:LS", "121:Liberia:LR",
                    "122:Libyan Arab Jamahiriya:LY", "123:Liechtenstein:LI", "124:Lithuania:LT","125:Luxembourg:LU",
                    "126:Macau:MO", "127:Macedonia:MK", "128:Madagascar:MG", "129:Malawi:MW", "130:Malaysia:MY",
                    "131:Maldives:MV", "132:Mali:ML", "133:Malta:MT", "134:Marshall Islands:MH", "135:Martinique:MQ",
                    "136:Mauritania:MR", "137:Mauritius:MU", "138:Mayotte:YT", "139:Mexico:MX", "140:Micronesia:FM", 
                    "141:Moldova, Republic of:MD", "142:Monaco:MC", "143:Mongolia:MN", "144:Montserrat:MS", "145:Morocco:MA",
                    "146:Mozambique:MZ", "147:Myanmar:MM", "148:Namibia:NA", "149:Nauru:NR", "150:Nepal:NP", "151:Netherlands:NL",
                    "152:Netherlands Antilles:AN", "153:New Caledonia:NC", "154:New Zealand:NZ", "155:Nicaragua:NI", 
                    "156:Niger:NE", "157:Nigeria:NG", "158:Niue:NU", "159:Norfolk Island:NF", "160:Northern Mariana Islands:MP",
                    "161:Norway:NO", "162:Oman:OM", "163:Pakistan:PK", "164:Palau:PW", "165:Panama:PA", "166:Papua New Guinea:PG",
                    "167:Paraguay:PY", "168:Peru:PE", "169:Philippines:PH", "170:Pitcairn:PN", "171:Poland:PL", "172:Portugal:PT", 
                    "173:Puerto Rico:PR", "174:Qatar:QA", "175:Reunion:RE", "176:Romania:RO", "177:Russian Federation:RU",
                    "178:Rwanda:RW", "179:Saint Kitts and Nevis:KN", "180:Saint Lucia:LC", "181:Saint Vincent and Grenadines:VC", 
                    "182:Samoa:WS", "183:San Marino:SM", "184:Sao Tome and Principe:ST", "185:Saudi Arabia:SA", "186:Senegal:SN",
                    "187:Serbia and Montenegro:CS", "188:Seychelles:SC", "189:Sierra Leone:SL", "190:Singapore:SG", 
                    "191:Slovakia (Slovak Republic):SK", "192:Slovenia:SI", "193:Solomon Islands:SB", "194:Somalia:SO", 
                    "195:South Africa:ZA", "196:Spain:ES", "197:Sri Lanka:LK", "198:St. Helena:SH", "199:St. Pierre and Miquelon:PM",
                    "200:Sudan:SD", "201:Suriname:SR", "202:Svalbard and Jan Mayen Isl:SJ", "203:Swaziland:SZ", "204:Sweden:SE",
                    "205:Switzerland:CH", "206:Syrian Arab Republic:SY", "207:Taiwan:TW", "208:Tajikistan:TJ", "209:Tanzania:TZ",
                    "210:Thailand:TH", "211:Togo:TG", "212:Tokelau:TK", "213:Tonga:TO", "214:Trinidad and Tobago:TT", "215:Tunisia:TN",
                    "216:Turkey:TR", "217:Turkmenistan:TM", "218:Turks and Caicos Islands:TC", "219:Tuvalu:TV", "220:Uganda:UG",
                    "221:Ukraine:UA", "222:United Arab Emirates:AE","223:United Kingdom:GB", "224:United States Outlying:UM",
                    "225:Uruguay:UY", "226:Uzbekistan:UZ", "227:Vanuatu:VU", "228:Vatican City State:VA", "229:Venezuela:VE",
                    "230:Vietnam:VN", "231:Virgin Islands (British):VG", "232:Virgin Islands (U.S.):VI", "233:Wallis And Futuna Islands:WF",
                    "234:Western Sahara:EH", "235:Yemen:YE", "236:Zambia:ZM", "237:Zimbabwe:ZW" );


function init_table ($db, $table_name, $qry)
{
    $q = 'SELECT true FROM pg_tables WHERE tablename=\''.pg_escape_string ($table_name).'\'';
    if ($r = pg_query ($db, $q))
    {
        if (!pg_num_rows ($r))
        {
            if (($r = pg_query ($db, $qry)) && pg_result_status ($r))
                return (1);
        } // Table not exists?
        return (2);
    } // SQL query
    return (false);
} // init_table





echo "Initializing LTS system....\n";
// This initialization is for a PostreSQL backed LTS system
$b = new ltsBase(false);
if ($db = $b->dbConn())
{
    echo 'Session table...';
    $q = 'CREATE TABLE session (sid VARCHAR, uid INT DEFAULT 0, type SMALLINT DEFAULT 0, 
          value VARCHAR, name VARCHAR, ts TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW())';
    if (($r = init_table ($db, 'session', $q)) !== false)
    {
        if ($r == 1)
        {
            echo " Created!\n";
            $q = 'CREATE INDEX session_indx_sid ON session (sid)';
            pg_query ($db, $q);
            $q = 'CREATE INDEX session_indx_name ON session (name)';
            pg_query ($db, $q);
        } else echo " Existing...\n";
    } else echo "DB error\n";
    
    echo 'Backend table...';
    $q = 'CREATE TABLE backend (name VARCHAR, modtime INT, data VARCHAR, access INT DEFAULT 0)';
    if (($r = init_table ($db, 'backend', $q)) !== false)
    {
        if ($r == 1)
        {
            echo " Created!\n";
            $q = 'CREATE INDEX backend_indx_name ON backend (name)';
            pg_query ($db, $q);
        } else echo " Existing...\n";
    } else echo "DB error\n";
    
    echo 'Users table...';
    $q = 'CREATE TABLE users (id SERIAL, email VARCHAR, firstname VARCHAR, lastname VARCHAR,
          company VARCHAR, password VARCHAR, type SMALLINT DEFAULT 0, created TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
          last_login TIMESTAMP, blocked BOOLEAN DEFAULT FALSE, verified BOOLEAN DEFAULT FALSE)';
    if (($r = init_table ($db, 'users', $q)) !== false)
    {
        if ($r == 1)
        {
            echo " Created!\n";
            $q = 'CREATE INDEX users_indx_id ON users (id)';
            pg_query ($db, $q);
        } else echo " Existing...\n";
    } else echo "DB error\n";
        
    echo 'External keys table...';
    $q = 'CREATE TABLE ext_keys (id SERIAL, name VARCHAR)';
    if (($r = init_table ($db, 'ext_keys', $q)) !== false)
    {
        if ($r == 1)
        {
            echo " Created!\n";
            $q = 'CREATE INDEX ext_keys_indx_id ON ext_keys (id)';
            pg_query ($db, $q);
            $q = 'CREATE INDEX ext_keys_indx_name ON ext_keys (name)';
            pg_query ($db, $q);
        } else echo " Existing...\n";
    } else echo "DB error\n";
        
    echo 'External data table...';
    $q = 'CREATE TABLE ext_data (id INT DEFAULT 0, rid INT, created TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
          modified TIMESTAMP WITHOUT TIME ZONE, data VARCHAR, uid INT)';
    if (($r = init_table ($db, 'ext_data', $q)) !== false)
    {
        if ($r == 1)
        {
            echo " Created!\n";
            $q = 'CREATE INDEX ext_data_indx_id ON ext_data (id)';
            pg_query ($db, $q);
        } else echo " Existing...\n";
    } else echo "DB error\n";
    
    
    echo 'Access table...';
    $q = 'CREATE TABLE access (host_ip VARCHAR, date TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(), browser VARCHAR)';
    if (($r = init_table ($db, 'access', $q)) !== false)
    {
        if ($r == 1)
        {
            echo "Created!\n";
            $q = 'CREATE INDEX access_indx_host_ip ON access (host_ip)';
            pg_query ($db, $q);
        } else echo " Existing...\n";
    } else echo "DB error\n";
    
    
    echo 'Country table...';
    $q = 'CREATE TABLE country (id SMALLINT, name VARCHAR, abv CHAR(2))';
    if (($r = init_table ($db, 'country', $q)) !== false)
    {
        if ($r == 1)
        {
            echo "Created!\n";
            $q = 'CREATE INDEX country_indx_id ON country (id)';
            pg_query ($db, $q);
            
            // INSERT DATA INTO TABLE
            foreach ($countries as $country)
            {
                $c = explode (':', $country);
                $q = 'INSERT INTO country (id, name, abv) VALUES ('.$c[0].',\''.pg_escape_string ($c[1]).'\',\''.pg_escape_string ($c[2]).'\')';
                pg_query ($db, $q);
            } // FOREACH
        } else echo " Existing...\n";
    } else echo "DB error\n";
    
} // Has DB connection?

?>