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

trait Find {

    /**
     * @throws ObjectException
     * @throws DirectoryCreateException
     * @throws Exception
     */
    public function find(object $flags, object $options): void
    {
        //min max at second iteration is wrong

        $object = $this->object();
        Core::interactive();
        $options->iterations = $options->iterations ?? 128;
//        $encoded = htmlentities($string, ENT_QUOTES, 'UTF-8');
        $url_spec = $object->config('controller.dir.data') . 'Spec.json';
        $url_model = $object->config('controller.dir.data') . 'Model.json';
        $spec = $object->data_read($url_spec, 'spec');
        if(!$spec){
            throw new ErrorException('Spec file not found');
        }
        $char_to_key = $object->config('char.to.key');
        if($char_to_key === null){
            $char_to_key = [];
            $key_to_char = [];
            foreach($spec->data() as $nr => $record){
                $char_to_key[$record->token] = $nr;
                $key_to_char[$nr] = $record->token;
            }
            $object->config('char.to.key', $char_to_key);
            $object->config('key.to.char', $key_to_char);
        }
        $text = $options->text ?? null;
        /*
        if(property_exists($options, 'result_count')){

        } else {
            $options->result_count[] = 0;
        }
        */
//        $current_count = end($options->result_count) ?? 0;
        $current_pointer_min = $options->model_pointer_min ?? [0];
        $current_pointer_max = $options->model_pointer_max ?? [0];
        $search = [];
        if($text){
            $split = mb_str_split($text);
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
                    $search[] = $char_to_key[$char_block_64];
                    $skip+=63;
                }
                elseif(array_key_exists($char_block_63, $char_to_key)){
                    $search[] = $char_to_key[$char_block_63];
                    $skip+=62;
                }
                elseif(array_key_exists($char_block_62, $char_to_key)){
                    $search[] = $char_to_key[$char_block_62];
                    $skip+=61;
                }
                elseif(array_key_exists($char_block_61, $char_to_key)){
                    $search[] = $char_to_key[$char_block_61];
                    $skip+=60;
                }
                elseif(array_key_exists($char_block_60, $char_to_key)){
                    $search[] = $char_to_key[$char_block_60];
                    $skip+=59;
                }
                elseif(array_key_exists($char_block_59, $char_to_key)){
                    $search[] = $char_to_key[$char_block_59];
                    $skip+=58;
                }
                elseif(array_key_exists($char_block_58, $char_to_key)){
                    $search[] = $char_to_key[$char_block_58];
                    $skip+=57;
                }
                elseif(array_key_exists($char_block_57, $char_to_key)){
                    $search[] = $char_to_key[$char_block_57];
                    $skip+=56;
                }
                elseif(array_key_exists($char_block_56, $char_to_key)){
                    $search[] = $char_to_key[$char_block_56];
                    $skip+=55;
                }
                elseif(array_key_exists($char_block_55, $char_to_key)){
                    $search[] = $char_to_key[$char_block_55];
                    $skip+=54;
                }
                elseif(array_key_exists($char_block_54, $char_to_key)){
                    $search[] = $char_to_key[$char_block_54];
                    $skip+=53;
                }
                elseif(array_key_exists($char_block_53, $char_to_key)){
                    $search[] = $char_to_key[$char_block_53];
                    $skip+=52;
                }
                elseif(array_key_exists($char_block_52, $char_to_key)){
                    $search[] = $char_to_key[$char_block_52];
                    $skip+=51;
                }
                elseif(array_key_exists($char_block_51, $char_to_key)){
                    $search[] = $char_to_key[$char_block_51];
                    $skip+=50;
                }
                elseif(array_key_exists($char_block_50, $char_to_key)){
                    $search[] = $char_to_key[$char_block_50];
                    $skip+=49;
                }
                elseif(array_key_exists($char_block_49, $char_to_key)){
                    $search[] = $char_to_key[$char_block_49];
                    $skip+=48;
                }
                elseif(array_key_exists($char_block_48, $char_to_key)){
                    $search[] = $char_to_key[$char_block_48];
                    $skip+=47;
                }
                elseif(array_key_exists($char_block_47, $char_to_key)){
                    $search[] = $char_to_key[$char_block_47];
                    $skip+=46;
                }
                elseif(array_key_exists($char_block_46, $char_to_key)){
                    $search[] = $char_to_key[$char_block_46];
                    $skip+=45;
                }
                elseif(array_key_exists($char_block_45, $char_to_key)){
                    $search[] = $char_to_key[$char_block_45];
                    $skip+=44;
                }
                elseif(array_key_exists($char_block_44, $char_to_key)){
                    $search[] = $char_to_key[$char_block_44];
                    $skip+=43;
                }
                elseif(array_key_exists($char_block_43, $char_to_key)){
                    $search[] = $char_to_key[$char_block_43];
                    $skip+=42;
                }
                elseif(array_key_exists($char_block_42, $char_to_key)){
                    $search[] = $char_to_key[$char_block_42];
                    $skip+=41;
                }
                elseif(array_key_exists($char_block_41, $char_to_key)){
                    $search[] = $char_to_key[$char_block_41];
                    $skip+=40;
                }
                elseif(array_key_exists($char_block_40, $char_to_key)){
                    $search[] = $char_to_key[$char_block_40];
                    $skip+=39;
                }
                elseif(array_key_exists($char_block_39, $char_to_key)){
                    $search[] = $char_to_key[$char_block_39];
                    $skip+=38;
                }
                elseif(array_key_exists($char_block_38, $char_to_key)){
                    $search[] = $char_to_key[$char_block_38];
                    $skip+=37;
                }
                elseif(array_key_exists($char_block_37, $char_to_key)){
                    $search[] = $char_to_key[$char_block_37];
                    $skip+=36;
                }
                elseif(array_key_exists($char_block_36, $char_to_key)){
                    $search[] = $char_to_key[$char_block_36];
                    $skip+=35;
                }
                elseif(array_key_exists($char_block_35, $char_to_key)){
                    $search[] = $char_to_key[$char_block_35];
                    $skip+=34;
                }
                elseif(array_key_exists($char_block_34, $char_to_key)){
                    $search[] = $char_to_key[$char_block_34];
                    $skip+=33;
                }
                elseif(array_key_exists($char_block_33, $char_to_key)){
                    $search[] = $char_to_key[$char_block_33];
                    $skip+=32;
                }
                elseif(array_key_exists($char_block_32, $char_to_key)){
                    $search[] = $char_to_key[$char_block_32];
                    $skip+=31;
                }
                elseif(array_key_exists($char_block_31, $char_to_key)){
                    $search[] = $char_to_key[$char_block_31];
                    $skip+=30;
                }
                elseif(array_key_exists($char_block_30, $char_to_key)){
                    $search[] = $char_to_key[$char_block_30];
                    $skip+=29;
                }
                elseif(array_key_exists($char_block_29, $char_to_key)){
                    $search[] = $char_to_key[$char_block_29];
                    $skip+=28;
                }
                elseif(array_key_exists($char_block_28, $char_to_key)){
                    $search[] = $char_to_key[$char_block_28];
                    $skip+=27;
                }
                elseif(array_key_exists($char_block_27, $char_to_key)){
                    $search[] = $char_to_key[$char_block_27];
                    $skip+=26;
                }
                elseif(array_key_exists($char_block_26, $char_to_key)){
                    $search[] = $char_to_key[$char_block_26];
                    $skip+=25;
                }
                elseif(array_key_exists($char_block_25, $char_to_key)){
                    $search[] = $char_to_key[$char_block_25];
                    $skip+=24;
                }
                elseif(array_key_exists($char_block_24, $char_to_key)){
                    $search[] = $char_to_key[$char_block_24];
                    $skip+=23;
                }
                elseif(array_key_exists($char_block_23, $char_to_key)){
                    $search[] = $char_to_key[$char_block_23];
                    $skip+=22;
                }
                elseif(array_key_exists($char_block_22, $char_to_key)){
                    $search[] = $char_to_key[$char_block_22];
                    $skip+=21;
                }
                elseif(array_key_exists($char_block_21, $char_to_key)){
                    $search[] = $char_to_key[$char_block_21];
                    $skip+=20;
                }
                elseif(array_key_exists($char_block_20, $char_to_key)){
                    $search[] = $char_to_key[$char_block_20];
                    $skip+=19;
                }
                elseif(array_key_exists($char_block_19, $char_to_key)){
                    $search[] = $char_to_key[$char_block_19];
                    $skip+=18;
                }
                elseif(array_key_exists($char_block_18, $char_to_key)){
                    $search[] = $char_to_key[$char_block_18];
                    $skip+=17;
                }
                elseif(array_key_exists($char_block_17, $char_to_key)){
                    $search[] = $char_to_key[$char_block_17];
                    $skip+=16;
                }
                elseif(array_key_exists($char_block_16, $char_to_key)){
                    $search[] = $char_to_key[$char_block_16];
                    $skip+=15;
                }
                elseif(array_key_exists($char_block_15, $char_to_key)){
                    $search[] = $char_to_key[$char_block_15];
                    $skip+=14;
                }
                elseif(array_key_exists($char_block_14, $char_to_key)){
                    $search[] = $char_to_key[$char_block_14];
                    $skip+=13;
                }
                elseif(array_key_exists($char_block_13, $char_to_key)){
                    $search[] = $char_to_key[$char_block_13];
                    $skip+=12;
                }
                elseif(array_key_exists($char_block_12, $char_to_key)){
                    $search[] = $char_to_key[$char_block_12];
                    $skip+=11;
                }
                elseif(array_key_exists($char_block_11, $char_to_key)){
                    $search[] = $char_to_key[$char_block_11];
                    $skip+=10;
                }
                elseif(array_key_exists($char_block_10, $char_to_key)){
                    $search[] = $char_to_key[$char_block_10];
                    $skip+=9;
                }
                elseif(array_key_exists($char_block_9, $char_to_key)){
                    $search[] = $char_to_key[$char_block_9];
                    $skip+=8;
                }
                elseif(array_key_exists($char_block_8, $char_to_key)){
                    $search[] = $char_to_key[$char_block_8];
                    $skip+=7;
                }
                elseif(array_key_exists($char_block_7, $char_to_key)){
                    $search[] = $char_to_key[$char_block_7];
                    $skip+=6;
                }
                elseif(array_key_exists($char_block_6, $char_to_key)){
                    $search[] = $char_to_key[$char_block_6];
                    $skip+=5;
                }
                elseif(array_key_exists($char_block_5, $char_to_key)){
                    $search[] = $char_to_key[$char_block_5];
                    $skip+=4;
                }
                elseif(array_key_exists($char_block_4, $char_to_key)){
                    $search[] = $char_to_key[$char_block_4];
                    $skip+=3;
                }
                elseif(array_key_exists($char_block_3, $char_to_key)){
                    $search[] = $char_to_key[$char_block_3];
                    $skip+=2;
                }
                elseif(array_key_exists($char_block_2, $char_to_key)){
                    $search[] = $char_to_key[$char_block_2];
                    $skip++;
                }
                elseif(array_key_exists($char, $char_to_key)){
                    $search[] = $char_to_key[$char];
                }
            }
        }
        $count = 0;
        $result_max = 10000; //max_results
        $key_to_char = $object->config('key.to.char');
        $model  = $object->data('model.data');
        if($model === null){
            $model = $object->data_read($url_model);
            if(!$model){
                throw new ErrorException('Model file not found');
            }
            $model = $model->data();
            $object->data('model.data', $model);
        }
        $search_count = count($search);
        $token_count = $search_count;
        $model_count = $object->config('model.count');
        if($model_count === null){
            $model_count = count($model);
            $object->config('model.count', $model_count);
        }
        $result = [];
        $result_header = [];
        $start_fresh = false;
        if(property_exists($options, 'model_pointer_min')){
            $pointer_start = end($options->model_pointer_min);
        } else {
            $pointer_start = 0;
            $start_fresh = true;
        }
        if(property_exists($options, 'model_pointer_max')){
            $pointer_end = end($options->model_pointer_max);
        } else {
            $pointer_end = $model_count;
        }
        $pointer_min = [];
        $pointer_max = [];
        $skip = 0;
        for($nr = $pointer_start; $nr < $pointer_end; $nr++){
            if($skip > 0){
                $skip--;
                continue;
            }
//            $token_id = $model[$nr];
            $is_found = false;
            $context_window = [];
            for($i = 0; $i < $search_count; $i++){
                $char = $model[$nr + $i] ?? null;
                $context_window[] = $char;
            }
            if($context_window === $search){
                $is_found = true;
                $skip += $search_count - 1;
                /*
                if(($pointer_end - $pointer_start)  > ($model_count / 8)) {
                    $options->model_pointer_max[] = $pointer_end * 0.95;
                }
                */

            }
            if($is_found){
                $part = '';
                $max = $nr + $search_count + 1;
                //might need conversion for 4 spaces, 3 spaces, 2 spaces
                for($i = $nr + $search_count; $i < $max; $i++){
                    $part .= $key_to_char[$model[$i]] ?? '';
                }
                $pointer_min[$part][] = $nr;
                /*
                $header = [];
                for($i = $nr; $i >= 0; $i--){
                    if($model[$i] === $char_to_key["<HEADER_START>"]){
                        break;
                    }
                }
                $start_header = $i + 1;
                for($i = $start_header; $i < $model_count;  $i++){
                    if( $model[$i] === $char_to_key["<HEADER_END>"]){
                        break;
                    } else {
                        $header[] = $key_to_char[$model[$i]] ?? '';
                    }
                }
                $header = Core::object(implode('', $header));
                */
                //grep line from the beginning of the line to the end line
                /*
                for($i = $nr; $i >= 0; $i--){
                    if($model[$i] === $char_to_key["\n"]){
                        break; //found start of line (at +1)
                    }
                }
                $start = $i + 1;
                for($i = $nr; $i < $model_count;  $i++){
                    if( $model[$i] === $char_to_key["\n"]){
                        break;
                    }
                }
                $end = $i;
                $line = '';
                for($i = $start; $i <= $end; $i++){
                    $line .= $key_to_char[$model[$i]] ?? '';
                }
                */
                if(array_key_exists($part, $result)){
                    $result[$part]++;
//                    $result_header[$part][] = Core::object($header, Core::JSON_LINE);
                } else {
                    $result[$part] = 1;
//                    $result_header[$part][] = Core::object($header, Core::JSON_LINE);
                }
                $count++;
                $pointer_max[$part][] = $nr + $search_count;
                /*
                if(
                    property_exists($options, 'result_count') &&
                    $options->result_count > 1 &&
                    $count >= $options->result_count
                ){
                    break;
                }
                if($count >= $result_max){
                    break;
                }
                */
            }
            /*
            elseif(
                $start_fresh === false &&
                $pointer_max > 0 &&
                $nr > $pointer_max &&
                $pointer_max > $pointer_min // * 1.02      //minimal growth of 2%
            ){
                break;
            }
            */
        }
        arsort($result, SORT_NATURAL);
        $nr = 0;
        $max = 10;
        $top_result = [];
        foreach($result as $part => $appearance){
            $top_result[$part] = round(($appearance / $count) * 100, 2);
            $nr++;
            if($nr > $max){
                break;
            }
        }
        $top = [];
        foreach($top_result as $part => $appearance){
            $multiplier = (int) $appearance;
            for($i=0; $i < $multiplier; $i++){
                $top[] = $part;
            }
        }
        if(!array_key_exists(0, $top)){

            echo 'No results found' . PHP_EOL;
            exit(0);
        }
        $key_rand = array_rand($top);
        $search[] = $char_to_key[$top[$key_rand]] ?? null;
        $text = '';
        foreach($search as $nr => $key){
            if(array_key_exists($key, $key_to_char)){
                $text .= $key_to_char[$key];
            }
        }
        if(property_exists($options, 'iteration_count')){
            $options->iteration_count++;
        } else {
            $options->iteration_count = 1;
        }
        if($options->iteration_count > $options->iterations){
            exit(0);
        }
        $options->text = $text;
        if(!property_exists($options, 'model_pointer_min')){
            $options->model_pointer_min = [];
        }
        if(!property_exists($options, 'model_pointer_max')){
            $options->model_pointer_max = [];
        }
        if(!property_exists($options, 'result_count')){
            $options->result_count = [];
        }
        $options->model_pointer_min[] = reset($pointer_min[$top[$key_rand]]);

        /*
        $end = end($options->model_pointer_max);
        if(
            $end > 0 &&
            $current_pointer_max > $end //* 1.05
        ){ //pointer max can grow max. 5% or else it will add 1 token to the last pointer
            $pointer_max = $end;
        }
        */
        /*
        if(
            $end > 0 &&
            $count === 1
        ){
            $pointer_max = $pointer_min;
        }
        */
        $object->config('time.duration', round(microtime(true) - $object->config('time.start'), 2));
        $count_last = end($options->result_count);
        $timeout_duplicate_find = false;
        if($count_last > 0 && $count_last === $count){
            //same search count
            if($count === 1){
                usleep(500000); //nice speed
            }
            elseif($object->config('time.duration') >= 60){
                $options->model_pointer_max[] = reset($options->model_pointer_min) + $search_count + 1;
                $count = 1;
                $timeout_duplicate_find = true;
            }
        }
        elseif($count_last > 0 && $count_last < $count){
            //spread search
//            $pointer_max = $pointer_min;
            if($count === 1){
                usleep(500000); //nice speed
            }
        }
        elseif($count_last > 0 && $count_last > $count){
            //narrow search
//            $pointer_max = $pointer_min;
        }
        if($timeout_duplicate_find === false){
            $options->model_pointer_max[] = end($pointer_max[$top[$key_rand]]);
        }
        $options->result_count[] = $count;
        echo Cli::tput('cursor.up') . Cli::tput('erase.line');
        if(strlen($text) > 80){
            $text = substr($text, -80);
        }
        echo 'Duration: ' . $object->config('time.duration') . ', Tokens: ' . $token_count . ', T/sec: ' . round($token_count / $object->config('time.duration'), 2)  . ', Count: ' . $count . ' total: '. $model_count . ', start: '. $pointer_start . ', min: ' . reset($pointer_min[$top[$key_rand]]) .', max: ' . end($pointer_max[$top[$key_rand]]) . ' ' . str_replace("\n", '<br>', $text) . PHP_EOL;
//        usleep(5000);
        $this->find($flags, $options);
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
        $header['file'] = $file->name;
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
        foreach($split as $nr => $char){
            if(array_key_exists($char, $char_to_key)){
                $transform[] = $char_to_key[$char];
            }
        }
        $transform[] = $char_to_key['<EOF>'] ?? null;
        $file->transform = $transform;
        return $file;
    }


}


