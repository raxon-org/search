<?php
namespace Package\Raxon\Search\Trait;

use Error;
use ErrorException;
use Exception;
use Raxon\Config;
use Raxon\Exception\DirectoryCreateException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Cli;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Module\SharedMemory;
use Raxon\Module\Time;

trait Token {

    /**
     * @throws ObjectException
     * @throws DirectoryCreateException
     * @throws Exception
     */
    public function spec(object $flags, object $options): void
    {
        $object = $this->object();
//        $encoded = htmlentities($string, ENT_QUOTES, 'UTF-8');


        $spec = [];
        $spec[] = ' ';
        $spec[] = '  ';
        $spec[] = '   ';
        $spec[] = '    ';
        $spec[] = '        ';
        $spec[] = '<EOF>';
        $spec[] = '<HEADER_START>';
        $spec[] = '<HEADER_END>';
        $spec[] = "\n";
        $spec[] = "\r";
        $spec[] = "\t";
        $spec[] = "\v";
        $spec[] = "\0";
        $spec[] = 'a';
        $spec[] = 'b';
        $spec[] = 'c';
        $spec[] = 'd';
        $spec[] = 'e';
        $spec[] = 'f';
        $spec[] = 'g';
        $spec[] = 'h';
        $spec[] = 'i';
        $spec[] = 'j';
        $spec[] = 'k';
        $spec[] = 'l';
        $spec[] = 'm';
        $spec[] = 'n';
        $spec[] = 'o';
        $spec[] = 'p';
        $spec[] = 'q';
        $spec[] = 'r';
        $spec[] = 's';
        $spec[] = 't';
        $spec[] = 'u';
        $spec[] = 'v';
        $spec[] = 'w';
        $spec[] = 'x';
        $spec[] = 'y';
        $spec[] = 'z';
        $spec[] = 'A';
        $spec[] = 'B';
        $spec[] = 'C';
        $spec[] = 'D';
        $spec[] = 'E';
        $spec[] = 'F';
        $spec[] = 'G';
        $spec[] = 'H';
        $spec[] = 'I';
        $spec[] = 'J';
        $spec[] = 'K';
        $spec[] = 'L';
        $spec[] = 'M';
        $spec[] = 'N';
        $spec[] = 'O';
        $spec[] = 'P';
        $spec[] = 'Q';
        $spec[] = 'R';
        $spec[] = 'S';
        $spec[] = 'T';
        $spec[] = 'U';
        $spec[] = 'V';
        $spec[] = 'W';
        $spec[] = 'X';
        $spec[] = 'Y';
        $spec[] = 'Z';
        $spec[] = '0';
        $spec[] = '1';
        $spec[] = '2';
        $spec[] = '3';
        $spec[] = '4';
        $spec[] = '5';
        $spec[] = '6';
        $spec[] = '7';
        $spec[] = '8';
        $spec[] = '9';
        $spec[] = '`';
        $spec[] = '~';
        $spec[] = '!';
        $spec[] = '@';
        $spec[] = '#';
        $spec[] = '$';
        $spec[] = '%';
        $spec[] = '^';
        $spec[] = '&';
        $spec[] = '*';
        $spec[] = '(';
        $spec[] = ')';
        $spec[] = '-';
        $spec[] = '_';
        $spec[] = '=';
        $spec[] = '+';
        $spec[] = '[';
        $spec[] = ']';
        $spec[] = '{';
        $spec[] = '}';
        $spec[] = '|';
        $spec[] = '\\';
        $spec[] = ';';
        $spec[] = ':';
        $spec[] = '\'';
        $spec[] = '"';
        $spec[] = '<';
        $spec[] = '>';
        $spec[] = ',';
        $spec[] = '.';
        $spec[] = '/';
        $spec[] = '?';

        $url_spec = $object->config('controller.dir.data') . 'Spec.json';
        $url_output = $object->config('controller.dir.data') . 'Model.json';
        File::delete($url_spec);
        File::delete($url_output);
        File::write($url_spec, Core::object($spec, Core::JSON));
        $dir = new Dir();
        $read = $dir->read('/Application', true);
        $model = [];
        if($read){
            foreach($read as $file){
                if($file->type === File::TYPE){
                    $file->extension = File::extension($file->url);
                    if(
                        in_array(
                            $file->extension,
                            [
                                'php',
                                'html',
                                'css',
                                'js',
                                'tpl',
                                '.md'
                            ], true
                        )
                    ){
                        $file->read = File::read($file->url);
                        $file = $this->transform($file, $spec);
                        if(property_exists($file, 'transform')){
                            foreach ($file->transform as $token) {
                                $model[] = $token;
                            }
                        }
                    }
                }
            }
        }
        File::write($url_output, Core::object($model, Core::JSON));
    }

    /**
     * @throws ObjectException
     */
    public function transform(object $file, array $spec): object
    {
        $object = $this->object();
        $char_to_key = $object->config('char.to.key');
        if($char_to_key === null){
            $char_to_key = [];
            foreach($spec as $nr => $char){
                $char_to_key[$char] = $nr;
            }
            $object->config('char.to.key', $char_to_key);
            $object->config('key.to.char', $spec);
        }
        $header = [];
        $header['url'] = $file->url;
        $header['name'] = $file->name;
        $header['type'] = $file->type;
        $header['mtime'] = File::mtime($file->url);
        $header['size'] = File::size($file->url);
        $header['extension'] = $file->extension;;
        $header = Core::object($header, Core::JSON_LINE);

        $transform = [];
        $transform[] = $char_to_key['<HEADER_START>'] ?? null;
        $split = mb_str_split($header);
        foreach($split as $nr => $char){
            if(array_key_exists($char, $char_to_key)){
                $transform[] = $char_to_key[$char];
            }
        }
        $transform[] = $char_to_key['<HEADER_END>'] ?? null;
        $split = mb_str_split($file->read);
        $skip = 0;
        foreach($split as $nr => $char){
            if($skip > 0){
                $skip--;
                continue;
            }
            $block_2 = [];
            $block_2[] = $char;
            $block_2[] = $split[$nr + 1] ?? null;
            $block_3 = $block_2;
            $block_3[] = $split[$nr + 2] ?? null;
            $block_4 = $block_3;
            $block_4[] = $split[$nr + 3] ?? null;
            $block_5 = $block_4;
            $block_5[] = $split[$nr + 4] ?? null;
            $block_6 = $block_5;
            $block_6[] = $split[$nr + 5] ?? null;
            $block_7 = $block_6;
            $block_7[] = $split[$nr + 6] ?? null;
            $block_8 = $block_7;
            $block_8[] = $split[$nr + 7] ?? null;
            $block_9 = $block_8;
            $block_9[] = $split[$nr + 8] ?? null;
            $block_10 = $block_9;
            $block_10[] = $split[$nr + 9] ?? null;
            $block_11 = $block_10;
            $block_11[] = $split[$nr + 10] ?? null;
            $block_12 = $block_11;
            $block_12[] = $split[$nr + 11] ?? null;
            $block_13 = $block_12;
            $block_13[] = $split[$nr + 12] ?? null;
            $block_14 = $block_13;
            $block_14[] = $split[$nr + 13] ?? null;
            $block_15 = $block_14;
            $block_15[] = $split[$nr + 14] ?? null;
            $block_16 = $block_15;
            $block_16[] = $split[$nr + 15] ?? null;
            $block_17 = $block_16;
            $block_17[] = $split[$nr + 16] ?? null;
            $block_18 = $block_17;
            $block_18[] = $split[$nr + 17] ?? null;
            $block_19 = $block_18;
            $block_19[] = $split[$nr + 18] ?? null;
            $block_20 = $block_19;
            $block_20[] = $split[$nr + 19] ?? null;
            $block_21 = $block_20;
            $block_21[] = $split[$nr + 20] ?? null;
            $block_22 = $block_21;
            $block_22[] = $split[$nr + 21] ?? null;
            $block_23 = $block_22;
            $block_23[] = $split[$nr + 22] ?? null;
            $block_24 = $block_23;
            $block_24[] = $split[$nr + 23] ?? null;
            $block_25 = $block_24;
            $block_25[] = $split[$nr + 24] ?? null;
            $block_26 = $block_25;
            $block_26[] = $split[$nr + 25] ?? null;
            $block_27 = $block_26;
            $block_27[] = $split[$nr + 26] ?? null;
            $block_28 = $block_27;
            $block_28[] = $split[$nr + 27] ?? null;
            $block_29 = $block_28;
            $block_29[] = $split[$nr + 28] ?? null;
            $block_30 = $block_29;
            $block_30[] = $split[$nr + 29] ?? null;
            $block_31 = $block_30;
            $block_31[] = $split[$nr + 30] ?? null;
            $block_32 = $block_31;
            $block_32[] = $split[$nr + 31] ?? null;
            $block_33 = $block_32;
            $block_33[] = $split[$nr + 32] ?? null;
            $block_34 = $block_33;
            $block_34[] = $split[$nr + 33] ?? null;
            $block_35 = $block_34;
            $block_35[] = $split[$nr + 34] ?? null;
            $block_36 = $block_35;
            $block_36[] = $split[$nr + 35] ?? null;
            $block_37 = $block_36;
            $block_37[] = $split[$nr + 36] ?? null;
            $block_38 = $block_37;
            $block_38[] = $split[$nr + 37] ?? null;
            $block_39 = $block_38;
            $block_39[] = $split[$nr + 38] ?? null;
            $block_40 = $block_39;
            $block_40[] = $split[$nr + 39] ?? null;
            $block_41 = $block_40;
            $block_41[] = $split[$nr + 40] ?? null;
            $block_42 = $block_41;
            $block_42[] = $split[$nr + 41] ?? null;
            $block_43 = $block_42;
            $block_43[] = $split[$nr + 42] ?? null;
            $block_44 = $block_43;
            $block_44[] = $split[$nr + 43] ?? null;
            $block_45 = $block_44;
            $block_45[] = $split[$nr + 44] ?? null;
            $block_46 = $block_45;
            $block_46[] = $split[$nr + 45] ?? null;
            $block_47 = $block_46;
            $block_47[] = $split[$nr + 46] ?? null;
            $block_48 = $block_47;
            $block_48[] = $split[$nr + 47] ?? null;
            $block_49 = $block_48;
            $block_49[] = $split[$nr + 48] ?? null;
            $block_50 = $block_49;
            $block_50[] = $split[$nr + 49] ?? null;
            $block_51 = $block_50;
            $block_51[] = $split[$nr + 50] ?? null;
            $block_52 = $block_51;
            $block_52[] = $split[$nr + 51] ?? null;
            $block_53 = $block_52;
            $block_53[] = $split[$nr + 52] ?? null;
            $block_54 = $block_53;
            $block_54[] = $split[$nr + 53] ?? null;
            $block_55 = $block_54;
            $block_55[] = $split[$nr + 54] ?? null;
            $block_56 = $block_55;
            $block_56[] = $split[$nr + 55] ?? null;
            $block_57 = $block_56;
            $block_57[] = $split[$nr + 56] ?? null;
            $block_58 = $block_57;
            $block_58[] = $split[$nr + 57] ?? null;
            $block_59 = $block_58;
            $block_59[] = $split[$nr + 58] ?? null;
            $block_60 = $block_59;
            $block_60[] = $split[$nr + 59] ?? null;
            $block_61 = $block_60;
            $block_61[] = $split[$nr + 60] ?? null;
            $block_62 = $block_61;
            $block_62[] = $split[$nr + 61] ?? null;
            $block_63 = $block_62;
            $block_63[] = $split[$nr + 62] ?? null;
            $block_64 = $block_63;
            $block_64[] = $split[$nr + 63] ?? null;
            $char_block_64 = implode('', $block_64);
            $char_block_63 = implode('', $block_63);
            $char_block_62 = implode('', $block_62);
            $char_block_61 = implode('', $block_61);
            $char_block_60 = implode('', $block_60);
            $char_block_59 = implode('', $block_59);
            $char_block_58 = implode('', $block_58);
            $char_block_57  = implode('', $block_57);
            $char_block_56 = implode('', $block_56);
            $char_block_55 = implode('', $block_55);
            $char_block_54 = implode('', $block_54);
            $char_block_53 = implode('', $block_53);
            $char_block_52 = implode('', $block_52);
            $char_block_51 = implode('', $block_51);
            $char_block_50 = implode('', $block_50);
            $char_block_49 = implode('', $block_49);
            $char_block_48 = implode('', $block_48);
            $char_block_47 = implode('', $block_47);
            $char_block_46 = implode('', $block_46);
            $char_block_45 = implode('', $block_45);
            $char_block_44 = implode('', $block_44);
            $char_block_43 = implode('', $block_43);
            $char_block_42 = implode('', $block_42);
            $char_block_41 = implode('', $block_41);
            $char_block_40 = implode('', $block_40);
            $char_block_39 = implode('', $block_39);
            $char_block_38 = implode('', $block_38);
            $char_block_37 = implode('', $block_37);
            $char_block_36 = implode('', $block_36);
            $char_block_35 = implode('', $block_35);
            $char_block_34 = implode('', $block_34);
            $char_block_33 = implode('', $block_33);
            $char_block_32 = implode('', $block_32);
            $char_block_31 = implode('', $block_31);
            $char_block_30 = implode('', $block_30);
            $char_block_29 = implode('', $block_29);
            $char_block_28 = implode('', $block_28);
            $char_block_27 = implode('', $block_27);
            $char_block_26 = implode('', $block_26);
            $char_block_25 = implode('', $block_25);
            $char_block_24 = implode('', $block_24);
            $char_block_23 = implode('', $block_23);
            $char_block_22 = implode('', $block_22);
            $char_block_21 = implode('', $block_21);
            $char_block_20 = implode('', $block_20);
            $char_block_19 = implode('', $block_19);
            $char_block_18 = implode('', $block_18);
            $char_block_17 = implode('', $block_17);
            $char_block_16 = implode('', $block_16);
            $char_block_15 = implode('', $block_15);
            $char_block_14 = implode('', $block_14);
            $char_block_13 = implode('', $block_13);
            $char_block_12 = implode('', $block_12);
            $char_block_11 = implode('', $block_11);
            $char_block_10 = implode('', $block_10);
            $char_block_9 = implode('', $block_9);
            $char_block_8 = implode('', $block_8);
            $char_block_7 = implode('', $block_7);
            $char_block_6 = implode('', $block_6);
            $char_block_5 = implode('', $block_5);
            $char_block_4 = implode('', $block_4);
            $char_block_3 = implode('', $block_3);
            $char_block_2 = implode('', $block_2);

            if(array_key_exists($char_block_64, $char_to_key)){
                $transform[] = $char_to_key[$char_block_64] ?? null;
                $skip =+ 63;
            }
            elseif(array_key_exists($char_block_63, $char_to_key)){
                $transform[] = $char_to_key[$char_block_63] ?? null;
                $skip =+ 62;
            }
            elseif(array_key_exists($char_block_62, $char_to_key)){
                $transform[] = $char_to_key[$char_block_62] ?? null;
                $skip =+ 61;
            }
            elseif(array_key_exists($char_block_61, $char_to_key)){
                $transform[] = $char_to_key[$char_block_61] ?? null;
                $skip =+ 60;
            }
            elseif(array_key_exists($char_block_60, $char_to_key)){
                $transform[] = $char_to_key[$char_block_60] ?? null;
                $skip =+ 59;
            }
            elseif(array_key_exists($char_block_59, $char_to_key)){
                $transform[] = $char_to_key[$char_block_59] ?? null;
                $skip =+ 58;
            }
            elseif(array_key_exists($char_block_58, $char_to_key)){
                $transform[] = $char_to_key[$char_block_58] ?? null;
                $skip =+ 57;
            }
            elseif(array_key_exists($char_block_57, $char_to_key)){
                $transform[] = $char_to_key[$char_block_57] ?? null;
                $skip =+ 56;
            }
            elseif(array_key_exists($char_block_56, $char_to_key)){
                $transform[] = $char_to_key[$char_block_56] ?? null;
                $skip =+ 55;
            }
            elseif(array_key_exists($char_block_55, $char_to_key)){
                $transform[] = $char_to_key[$char_block_55] ?? null;
                $skip =+ 54;
            }
            elseif(array_key_exists($char_block_54, $char_to_key)){
                $transform[] = $char_to_key[$char_block_54] ?? null;
                $skip =+ 53;
            }
            elseif(array_key_exists($char_block_53, $char_to_key)){
                $transform[] = $char_to_key[$char_block_53] ?? null;
                $skip =+ 52;
            }
            elseif(array_key_exists($char_block_52, $char_to_key)){
                $transform[] = $char_to_key[$char_block_52] ?? null;
                $skip =+ 51;
            }
            elseif(array_key_exists($char_block_51, $char_to_key)){
                $transform[] = $char_to_key[$char_block_51] ?? null;
                $skip =+ 50;
            }
            elseif(array_key_exists($char_block_50, $char_to_key)){
                $transform[] = $char_to_key[$char_block_50] ?? null;
                $skip =+ 49;
            }
            elseif(array_key_exists($char_block_49, $char_to_key)){
                $transform[] = $char_to_key[$char_block_49] ?? null;
                $skip =+ 48;
            }
            elseif(array_key_exists($char_block_48, $char_to_key)){
                $transform[] = $char_to_key[$char_block_48] ?? null;
                $skip =+ 47;
            }
            elseif(array_key_exists($char_block_47, $char_to_key)){
                $transform[] = $char_to_key[$char_block_47] ?? null;
                $skip =+ 46;
            }
            elseif(array_key_exists($char_block_46, $char_to_key)){
                $transform[] = $char_to_key[$char_block_46] ?? null;
                $skip =+ 45;
            }
            elseif(array_key_exists($char_block_45, $char_to_key)){
                $transform[] = $char_to_key[$char_block_45] ?? null;
                $skip =+ 44;
            }
            elseif(array_key_exists($char_block_44, $char_to_key)){
                $transform[] = $char_to_key[$char_block_44] ?? null;
                $skip =+ 43;
            }
            elseif(array_key_exists($char_block_43, $char_to_key)){
                $transform[] = $char_to_key[$char_block_43] ?? null;
                $skip =+ 42;
            }
            elseif(array_key_exists($char_block_42, $char_to_key)){
                $transform[] = $char_to_key[$char_block_42] ?? null;
                $skip =+ 41;
            }
            elseif(array_key_exists($char_block_41, $char_to_key)){
                $transform[] = $char_to_key[$char_block_41] ?? null;
                $skip =+ 40;
            }
            elseif(array_key_exists($char_block_40, $char_to_key)){
                $transform[] = $char_to_key[$char_block_40] ?? null;
                $skip =+ 39;
            }
            elseif(array_key_exists($char_block_39, $char_to_key)){
                $transform[] = $char_to_key[$char_block_39] ?? null;
                $skip =+ 38;
            }
            elseif(array_key_exists($char_block_38, $char_to_key)){
                $transform[] = $char_to_key[$char_block_38] ?? null;
                $skip =+ 37;
            }
            elseif(array_key_exists($char_block_37, $char_to_key)){
                $transform[] = $char_to_key[$char_block_37] ?? null;
                $skip =+ 36;
            }
            elseif(array_key_exists($char_block_36, $char_to_key)){
                $transform[] = $char_to_key[$char_block_36] ?? null;
                $skip =+ 35;
            }
            elseif(array_key_exists($char_block_35, $char_to_key)){
                $transform[] = $char_to_key[$char_block_35] ?? null;
                $skip =+ 34;
            }
            elseif(array_key_exists($char_block_34, $char_to_key)){
                $transform[] = $char_to_key[$char_block_34] ?? null;
                $skip =+ 33;
            }
            elseif(array_key_exists($char_block_33, $char_to_key)){
                $transform[] = $char_to_key[$char_block_33] ?? null;
                $skip =+ 32;
            }
            elseif(array_key_exists($char_block_32, $char_to_key)){
                $transform[] = $char_to_key[$char_block_32] ?? null;
                $skip =+ 31;
            }
            elseif(array_key_exists($char_block_31, $char_to_key)){
                $transform[] = $char_to_key[$char_block_31] ?? null;
                $skip =+ 30;
            }
            elseif(array_key_exists($char_block_30, $char_to_key)){
                $transform[] = $char_to_key[$char_block_30] ?? null;
                $skip =+ 29;
            }
            elseif(array_key_exists($char_block_29, $char_to_key)){
                $transform[] = $char_to_key[$char_block_29] ?? null;
                $skip =+ 28;
            }
            elseif(array_key_exists($char_block_28, $char_to_key)){
                $transform[] = $char_to_key[$char_block_28] ?? null;
                $skip =+ 27;
            }
            elseif(array_key_exists($char_block_27, $char_to_key)){
                $transform[] = $char_to_key[$char_block_27] ?? null;
                $skip =+ 26;
            }
            elseif(array_key_exists($char_block_26, $char_to_key)){
                $transform[] = $char_to_key[$char_block_26] ?? null;
                $skip =+ 25;
            }
            elseif(array_key_exists($char_block_25, $char_to_key)){
                $transform[] = $char_to_key[$char_block_25] ?? null;
                $skip =+ 24;
            }
            elseif(array_key_exists($char_block_24, $char_to_key)){
                $transform[] = $char_to_key[$char_block_24] ?? null;
                $skip =+ 23;
            }
            elseif(array_key_exists($char_block_23, $char_to_key)){
                $transform[] = $char_to_key[$char_block_23] ?? null;
                $skip =+ 22;
            }
            elseif(array_key_exists($char_block_22, $char_to_key)){
                $transform[] = $char_to_key[$char_block_22] ?? null;
                $skip =+ 21;
            }
            elseif(array_key_exists($char_block_21, $char_to_key)){
                $transform[] = $char_to_key[$char_block_21] ?? null;
                $skip =+ 20;
            }
            elseif(array_key_exists($char_block_20, $char_to_key)){
                $transform[] = $char_to_key[$char_block_20] ?? null;
                $skip =+ 19;
            }
            elseif(array_key_exists($char_block_19, $char_to_key)){
                $transform[] = $char_to_key[$char_block_19] ?? null;
                $skip =+ 18;
            }
            elseif(array_key_exists($char_block_18, $char_to_key)){
                $transform[] = $char_to_key[$char_block_18] ?? null;
                $skip =+ 17;
            }
            elseif(array_key_exists($char_block_17, $char_to_key)){
                $transform[] = $char_to_key[$char_block_17] ?? null;
                $skip =+ 16;
            }
            elseif(array_key_exists($char_block_16, $char_to_key)){
                $transform[] = $char_to_key[$char_block_16] ?? null;
                $skip =+ 15;
            }
            elseif(array_key_exists($char_block_15, $char_to_key)){
                $transform[] = $char_to_key[$char_block_15] ?? null;
                $skip =+ 14;
            }
            elseif(array_key_exists($char_block_14, $char_to_key)){
                $transform[] = $char_to_key[$char_block_14] ?? null;
                $skip =+ 13;
            }
            elseif(array_key_exists($char_block_13, $char_to_key)){
                $transform[] = $char_to_key[$char_block_13] ?? null;
                $skip =+ 12;
            }
            elseif(array_key_exists($char_block_12, $char_to_key)){
                $transform[] = $char_to_key[$char_block_12] ?? null;
                $skip =+ 11;
            }
            elseif(array_key_exists($char_block_11, $char_to_key)){
                $transform[] = $char_to_key[$char_block_11] ?? null;
                $skip =+ 10;
            }
            elseif(array_key_exists($char_block_10, $char_to_key)){
                $transform[] = $char_to_key[$char_block_10] ?? null;
                $skip =+ 9;
            }
            elseif(array_key_exists($char_block_9, $char_to_key)){
                $transform[] = $char_to_key[$char_block_9] ?? null;
                $skip =+ 8;
            }
            elseif(array_key_exists($char_block_8, $char_to_key)){
                $transform[] = $char_to_key[$char_block_8] ?? null;
                $skip =+ 7;
            }
            elseif(array_key_exists($char_block_7, $char_to_key)){
                $transform[] = $char_to_key[$char_block_7] ?? null;
                $skip =+ 6;
            }
            elseif(array_key_exists($char_block_6, $char_to_key)){
                $transform[] = $char_to_key[$char_block_6] ?? null;
                $skip =+ 5;
            }
            elseif(array_key_exists($char_block_5, $char_to_key)){
                $transform[] = $char_to_key[$char_block_5] ?? null;
                $skip =+ 4;
            }
            elseif(array_key_exists($char_block_4, $char_to_key)){
                $transform[] = $char_to_key[$char_block_4] ?? null;
                $skip =+ 3;
            }
            elseif(array_key_exists($char_block_3, $char_to_key)){
                $transform[] = $char_to_key[$char_block_3] ?? null;
                $skip += 2;
            }
            elseif(array_key_exists($char_block_2, $char_to_key)){
                $transform[] = $char_to_key[$char_block_2] ?? null;
                $skip++;
            }
            elseif(array_key_exists($char, $char_to_key)){
                $transform[] = $char_to_key[$char];
            }
        }
        $transform[] = $char_to_key['<EOF>'] ?? null;
        $file->transform = $transform;
        return $file;
    }
}


