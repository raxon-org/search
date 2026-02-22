<?php
namespace Package\Raxon\Search\Trait;

use Error;
use ErrorException;
use Exception;
use Raxon\Config;
use Raxon\Exception\DirectoryCreateException;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Cli;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Module\Parallel;
use Raxon\Module\SharedMemory;
use Raxon\Module\Time;

trait Service {
    //512 0.36 T/sec
    const PARTITION_SIZE = 128;

    /**
     * @throws DirectoryCreateException
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function ask_word(object $flags, object $options): void
    {
        $object = $this->object();
        if(!property_exists($options, 'text')){
            throw new ErrorException('Missing text option');
        }
        $dir_input = $object->config('ramdisk.url') . '33/Model/Input/';
        $dir_output = $object->config('ramdisk.url') . '33/Model/Output/';
        $dir_stream = $object->config('ramdisk.url') . '33/Model/Stream/';
        $uuid = Core::uuid();
        Dir::create($dir_input, Dir::CHMOD);
        Dir::create($dir_output, Dir::CHMOD);
        Dir::create($dir_stream, Dir::CHMOD);
        $ask = (object) [
            'uuid' => $uuid,
            'text' => $options->text ?? null,
            'url'  => (object) [
                'input' => $dir_input . $uuid . $object->config('extension.json'),
                'output' => $dir_output . $uuid . $object->config('extension.json'),
                'stream' => $dir_stream . $uuid . $object->config('extension.json')
            ],
            'type' => 'word',
            'status' => 'init'
        ];
        $data = new Data();
        $data->set('ask', $ask);
        $data->write($ask->url->input);
        $start = true;
        //wait for output
        while(true){
            if(File::exist($ask->url->stream)){
                $read = $object->data_read($ask->url->stream);
                if($read === null){
                    File::delete($ask->url->input);
                    File::delete($ask->url->stream);
                }
                $stream = $read->get('stream');
                $token_count = count($stream);
                $bytes_count = 0;
                $columns = (int) Cli::tput('columns');
                $rows = (int) Cli::tput('rows');
                if(
                    in_array(
                        $read->get('status'),
                        [
                            'init',
                            'progress'
                        ]
                    )
                ){
                    $token = (object) [
                        'hit' => 0,
                        'partitions' => (object) [
                            'count' => self::PARTITION_SIZE,
                        ]
                    ];
                    if($start === true){
                        echo CLi::tput('cursor.position', [0, 0]);
                        for($nr = 0; $nr < $rows; $nr++){
                            echo str_repeat(' ', $columns);
                        }
                        $start = false;
                    }
                    echo CLi::tput('cursor.position', [0, 0]);
                    if(is_array($stream)){
                        foreach($stream as $token){
                            echo $token->token;
                            $bytes_count += mb_strlen($token->token);
                            if(property_exists($token, 'partitions')){
                                if(property_exists($token->partitions, 'enable')){
                                    $token->partitions->count = count($token->partitions->enable);
                                }
                            }
                        }
                    }
                    $duration = round(microtime(true) - $object->config('time.start'), 2);
                    if($duration > 0){
                        echo CLi::tput('cursor.position', [0, $rows-1]);
                        echo str_repeat(' ', $columns);
                        echo CLi::tput('cursor.position', [0, $rows-1]);
                        echo 'Token count: ' . $token_count . ', Speed: ' . round($token_count / $duration, 2) . ' T/sec, Bytes: '. $bytes_count . ' hit: ' . $token->hit . ', partitions: ' . $token->partitions->count;
                    }
                    usleep(300000);
                }
                elseif($read->get('status') === 'finish'){
                    $token = (object) [
                        'hit' => 0,
                        'partitions' => (object) [
                            'count' => self::PARTITION_SIZE,
                        ]
                    ];
                    echo CLi::tput('cursor.position', [0, 0]);
                    for($nr = 0; $nr < $rows; $nr++){
                        echo str_repeat(' ', $columns);
                    }
                    echo CLi::tput('cursor.position', [0, 1]);
                    if(is_array($stream)){
                        foreach($stream as $token){
                            echo $token->token;
                            $bytes_count += mb_strlen($token->token);
                            if(property_exists($token, 'partitions')){
                                if(property_exists($token->partitions, 'enable')){
                                    $token->partitions->count = count($token->partitions->enable);
                                }
                            }
                        }
                    }
                    $duration = round(microtime(true) - $object->config('time.start'), 2);
                    if($duration > 0){
                        echo CLi::tput('cursor.position', [0, $rows-1]);
                        echo str_repeat(' ', $columns);
                        echo CLi::tput('cursor.position', [0, $rows-1]);
                        echo 'Token count: ' . $token_count . ', Speed: ' . round($token_count / $duration, 2) . ' T/sec, Bytes: '. $bytes_count . ' hit: ' . $token->hit . ', partitions: ' . $token->partitions->count;
                    }
                    File::copy($ask->url->stream, $ask->url->output);
                    File::delete($ask->url->stream);
                    break;
                }
            } else {
                usleep(300000);
            }
        }
    }

    /**
     * @throws ObjectException
     * @throws DirectoryCreateException
     * @throws Exception
     */
    public function model(object $flags, object $options): void
    {
        $object = $this->object();
        Core::interactive();
        $options->iterations = $options->iterations ?? 128;
//        $encoded = htmlentities($string, ENT_QUOTES, 'UTF-8');
        $url_dictionary = $object->config('controller.dir.data') . 'Dictionary.json';
        $url_model = $object->config('controller.dir.data') . 'Model.json';
        $dictionary = $object->data_read($url_dictionary, 'dictionary');
        if(!$dictionary){
            throw new ErrorException('Dictionary file not found');
        }
        $char_to_key = $object->config('char.to.key');
        if($char_to_key === null){
            $char_to_key = [];
            $key_to_char = [];
            foreach($dictionary->data() as $nr => $record){
                if(property_exists($record, 'token')){
                    $char_to_key[$record->token] = $nr;
                    $key_to_char[$nr] = $record->token;
                }
            }
            $object->config('char.to.key', $char_to_key);
            $object->config('key.to.char', $key_to_char);
        }
        $key_to_char = $object->config('key.to.char');
        $dir_input = $object->config('ramdisk.url') . '33/Model/Input/';
        $dir_output = $object->config('ramdisk.url') . '33/Model/Output/';
        Dir::create($dir_input, Dir::CHMOD);
        Dir::create($dir_output, Dir::CHMOD);
        $data = $object->data_read($url_model, 'model');
        if(!$data){
            throw new ErrorException('Model file not found');
        }
        $data = $data->data();
        $partition = Core::array_partition($data , self::PARTITION_SIZE);
//        $object->data('partition', $partition);
        while(true){
            $object->data('service', $this);
            $object->config('time.duration', microtime(true) - $object->config('time.start'));
            if($object->config('time.duration') > 60){
                break;
            }
            $dir = new Dir();
            $read = $dir->read($dir_input);
            if(!$read){
                sleep(1);
                continue;
            }
            foreach($read as $file){
                if($file->type === File::TYPE){
                    $file->read = File::read($file->url);
                    $file->node = Core::object($file->read);
                    $search = $file->node->ask->text;
                    if(
                        property_exists($file->node, 'ask') &&
                        property_exists($file->node->ask, 'status') &&
                        in_array(
                            $file->node->ask->status,
                            [
                                'progress',
                                'finish'
                            ]
                        )
                    ){
                        continue;
                    }
                    switch($file->node->ask->type){
                        case 'token':
                            $partitions_enable = null;
                            $next_token = $this->token_next($partition, $file, $partitions_enable, $this->search($search, $char_to_key));
                            if($next_token !== null){
                                ddd($key_to_char[$next_token->token]);
                            }
                            break;
                        case 'word':
                            $next_word = '';
                            $ask = $file->node->ask;
                            $partitions_enable = null;
                            while(true){
                                $next_token = $this->token_next($partition, $file, $partitions_enable, $this->search($search, $char_to_key));
                                if($next_token === null){
                                    break;
                                }
                                $next_token_token = $next_token->token;
                                $partitions_enable = $next_token->partitions->enable ?? null;
                                $explode = explode(' ', $next_token_token, 2);
                                if(array_key_exists(1, $explode)){
                                    if($explode[0] !== ' '){
                                        $next_word .= $explode[0];
                                        $next_token->token = $explode[0];
                                        $next_token->key = $char_to_key[$explode[0]] ?? null;
                                    }
                                    if(!property_exists($ask, 'stream')){
                                        $ask->stream = [];
                                    }
                                    $ask->stream[] = $next_token;
                                    $ask->status = 'finish';
                                    $ask->word = $next_word;
                                    $data = new Data($ask);
                                    $data->write($ask->url->stream);
                                    break;
                                } else {
                                    $next_word .= $next_token_token;
                                }
                                $search .= $next_token_token;
                                $ask->status = 'progress';
                                if(!property_exists($ask, 'stream')){
                                    $ask->stream = [];
                                }
                                $ask->stream[] = $next_token;
                                $data = new Data($ask);
                                $data->write($ask->url->stream);
                            }
                    }
                }
            }
            usleep(500000);
        }
    }

    private function token_next(array $partition, object $file, array|null $partition_enable, array $search): null|object
    {
        $object = $this->object();
        $closures = [];
        $result_partition = [];
        $char_to_key = $object->config('char.to.key');
        foreach($partition as $partition_nr => $chunk) {
            if(
                $partition_enable !== null &&
                !in_array($partition_nr, $partition_enable, true)
            ){
                continue;
            }
            $closures[] = function () use (
                $object,
                $chunk,
                $file,
                $search,
                $partition_nr
            ) {
                $key_to_char = $object->config('key.to.char');
                $search_count = count($search);
                $result_closure = [];
                $skip = 0;
                $count = 0;
                foreach ($chunk as $nr => $key) {
                    $count++;
                    if ($skip > 0) {
                        $skip--;
                        continue;
                    }
                    $context_window = [];
                    for ($i = 0; $i < $search_count; $i++) {
                        $char = $chunk[$nr + $i] ?? null;
                        //should speedup search...
                        if (!in_array($char, $search, true)) {
                            $skip += $i;
                            break;
                        }
                        $context_window[] = $char;
                    }
                    if ($context_window === $search) {
                        $skip += $search_count - 1;
                        $part = '';
                        $max = $nr + $search_count + 1;
                        for ($i = $nr + $search_count; $i < $max; $i++) {
                            $part .= $key_to_char[$chunk[$i]] ?? '';
                        }
                        if (array_key_exists($part, $result_closure)) {
                            $result_closure[$part]->appearance++;
                            $result_closure[$part]->count = $count;
                        } else {
                            $result_closure[$part] = (object) [
                                'appearance' => 1,
                                'count' => $count,
                                'partition' => (object) [
                                    'nr' => $partition_nr,
                                ]
                            ];
                        }
                    }
                }
                return $result_closure;
            };
        }
        $list = Parallel::new()->execute($closures);
        $hit = 0;
        $count = 0;
        $enabled_partitions = [];
        foreach($list as $key => $item){
            if(
                $item !== null &&
                $item !== 'progress'
            ){
                if(is_array($item)){
                    foreach($item as $part => $node){
                        $hit += $node->appearance;
                        if($node->count > $count){
                            $count = $node->count;
                        }
                        //might be narrower then -2 (might be 1 after only)
                        for($i = $node->partition->nr; $i < $node->partition->nr + 2; $i++){
                            if(!in_array($i, $enabled_partitions, true)){
                                $enabled_partitions[] = $i;
                            }
                        }
                        if(!array_key_exists($part, $result_partition)){
                            $result_partition[$part] = $node->appearance;
                        } else {
                            $result_partition[$part] += $node->appearance;
                        }
                    }
                }
            }
        }
        arsort($result_partition, SORT_NATURAL);
        $result = [];
        foreach($result_partition as $part => $appearance){
            for($i = 0; $i < $appearance; $i++){
                $result[] = $part;
            }
        }
        if(array_key_exists(0, $result)){
            $key_rand = array_rand($result);
            $key = $char_to_key[$result[$key_rand]] ?? null;
            if($count > 0){
                return (object) [
                    'key' => $key,
                    'token' => $result[$key_rand],
                    'hit' => $hit,
                    'count' => $count,
                    'float' => $hit / $count,
                    'partitions' => [
                        'enable' => $enabled_partitions
                    ]
                ];
            }
        }
        return null;
    }

    private function search(string $text, array $char_to_key): array
    {
        $split = mb_str_split($text);
        $skip = 0;
        foreach ($split as $nr => $char) {
            if ($skip > 0) {
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
            $char_block_57 = implode('', $block_57);
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

            if (array_key_exists($char_block_64, $char_to_key)) {
                $search[] = $char_to_key[$char_block_64];
                $skip += 63;
            } elseif (array_key_exists($char_block_63, $char_to_key)) {
                $search[] = $char_to_key[$char_block_63];
                $skip += 62;
            } elseif (array_key_exists($char_block_62, $char_to_key)) {
                $search[] = $char_to_key[$char_block_62];
                $skip += 61;
            } elseif (array_key_exists($char_block_61, $char_to_key)) {
                $search[] = $char_to_key[$char_block_61];
                $skip += 60;
            } elseif (array_key_exists($char_block_60, $char_to_key)) {
                $search[] = $char_to_key[$char_block_60];
                $skip += 59;
            } elseif (array_key_exists($char_block_59, $char_to_key)) {
                $search[] = $char_to_key[$char_block_59];
                $skip += 58;
            } elseif (array_key_exists($char_block_58, $char_to_key)) {
                $search[] = $char_to_key[$char_block_58];
                $skip += 57;
            } elseif (array_key_exists($char_block_57, $char_to_key)) {
                $search[] = $char_to_key[$char_block_57];
                $skip += 56;
            } elseif (array_key_exists($char_block_56, $char_to_key)) {
                $search[] = $char_to_key[$char_block_56];
                $skip += 55;
            } elseif (array_key_exists($char_block_55, $char_to_key)) {
                $search[] = $char_to_key[$char_block_55];
                $skip += 54;
            } elseif (array_key_exists($char_block_54, $char_to_key)) {
                $search[] = $char_to_key[$char_block_54];
                $skip += 53;
            } elseif (array_key_exists($char_block_53, $char_to_key)) {
                $search[] = $char_to_key[$char_block_53];
                $skip += 52;
            } elseif (array_key_exists($char_block_52, $char_to_key)) {
                $search[] = $char_to_key[$char_block_52];
                $skip += 51;
            } elseif (array_key_exists($char_block_51, $char_to_key)) {
                $search[] = $char_to_key[$char_block_51];
                $skip += 50;
            } elseif (array_key_exists($char_block_50, $char_to_key)) {
                $search[] = $char_to_key[$char_block_50];
                $skip += 49;
            } elseif (array_key_exists($char_block_49, $char_to_key)) {
                $search[] = $char_to_key[$char_block_49];
                $skip += 48;
            } elseif (array_key_exists($char_block_48, $char_to_key)) {
                $search[] = $char_to_key[$char_block_48];
                $skip += 47;
            } elseif (array_key_exists($char_block_47, $char_to_key)) {
                $search[] = $char_to_key[$char_block_47];
                $skip += 46;
            } elseif (array_key_exists($char_block_46, $char_to_key)) {
                $search[] = $char_to_key[$char_block_46];
                $skip += 45;
            } elseif (array_key_exists($char_block_45, $char_to_key)) {
                $search[] = $char_to_key[$char_block_45];
                $skip += 44;
            } elseif (array_key_exists($char_block_44, $char_to_key)) {
                $search[] = $char_to_key[$char_block_44];
                $skip += 43;
            } elseif (array_key_exists($char_block_43, $char_to_key)) {
                $search[] = $char_to_key[$char_block_43];
                $skip += 42;
            } elseif (array_key_exists($char_block_42, $char_to_key)) {
                $search[] = $char_to_key[$char_block_42];
                $skip += 41;
            } elseif (array_key_exists($char_block_41, $char_to_key)) {
                $search[] = $char_to_key[$char_block_41];
                $skip += 40;
            } elseif (array_key_exists($char_block_40, $char_to_key)) {
                $search[] = $char_to_key[$char_block_40];
                $skip += 39;
            } elseif (array_key_exists($char_block_39, $char_to_key)) {
                $search[] = $char_to_key[$char_block_39];
                $skip += 38;
            } elseif (array_key_exists($char_block_38, $char_to_key)) {
                $search[] = $char_to_key[$char_block_38];
                $skip += 37;
            } elseif (array_key_exists($char_block_37, $char_to_key)) {
                $search[] = $char_to_key[$char_block_37];
                $skip += 36;
            } elseif (array_key_exists($char_block_36, $char_to_key)) {
                $search[] = $char_to_key[$char_block_36];
                $skip += 35;
            } elseif (array_key_exists($char_block_35, $char_to_key)) {
                $search[] = $char_to_key[$char_block_35];
                $skip += 34;
            } elseif (array_key_exists($char_block_34, $char_to_key)) {
                $search[] = $char_to_key[$char_block_34];
                $skip += 33;
            } elseif (array_key_exists($char_block_33, $char_to_key)) {
                $search[] = $char_to_key[$char_block_33];
                $skip += 32;
            } elseif (array_key_exists($char_block_32, $char_to_key)) {
                $search[] = $char_to_key[$char_block_32];
                $skip += 31;
            } elseif (array_key_exists($char_block_31, $char_to_key)) {
                $search[] = $char_to_key[$char_block_31];
                $skip += 30;
            } elseif (array_key_exists($char_block_30, $char_to_key)) {
                $search[] = $char_to_key[$char_block_30];
                $skip += 29;
            } elseif (array_key_exists($char_block_29, $char_to_key)) {
                $search[] = $char_to_key[$char_block_29];
                $skip += 28;
            } elseif (array_key_exists($char_block_28, $char_to_key)) {
                $search[] = $char_to_key[$char_block_28];
                $skip += 27;
            } elseif (array_key_exists($char_block_27, $char_to_key)) {
                $search[] = $char_to_key[$char_block_27];
                $skip += 26;
            } elseif (array_key_exists($char_block_26, $char_to_key)) {
                $search[] = $char_to_key[$char_block_26];
                $skip += 25;
            } elseif (array_key_exists($char_block_25, $char_to_key)) {
                $search[] = $char_to_key[$char_block_25];
                $skip += 24;
            } elseif (array_key_exists($char_block_24, $char_to_key)) {
                $search[] = $char_to_key[$char_block_24];
                $skip += 23;
            } elseif (array_key_exists($char_block_23, $char_to_key)) {
                $search[] = $char_to_key[$char_block_23];
                $skip += 22;
            } elseif (array_key_exists($char_block_22, $char_to_key)) {
                $search[] = $char_to_key[$char_block_22];
                $skip += 21;
            } elseif (array_key_exists($char_block_21, $char_to_key)) {
                $search[] = $char_to_key[$char_block_21];
                $skip += 20;
            } elseif (array_key_exists($char_block_20, $char_to_key)) {
                $search[] = $char_to_key[$char_block_20];
                $skip += 19;
            } elseif (array_key_exists($char_block_19, $char_to_key)) {
                $search[] = $char_to_key[$char_block_19];
                $skip += 18;
            } elseif (array_key_exists($char_block_18, $char_to_key)) {
                $search[] = $char_to_key[$char_block_18];
                $skip += 17;
            } elseif (array_key_exists($char_block_17, $char_to_key)) {
                $search[] = $char_to_key[$char_block_17];
                $skip += 16;
            } elseif (array_key_exists($char_block_16, $char_to_key)) {
                $search[] = $char_to_key[$char_block_16];
                $skip += 15;
            } elseif (array_key_exists($char_block_15, $char_to_key)) {
                $search[] = $char_to_key[$char_block_15];
                $skip += 14;
            } elseif (array_key_exists($char_block_14, $char_to_key)) {
                $search[] = $char_to_key[$char_block_14];
                $skip += 13;
            } elseif (array_key_exists($char_block_13, $char_to_key)) {
                $search[] = $char_to_key[$char_block_13];
                $skip += 12;
            } elseif (array_key_exists($char_block_12, $char_to_key)) {
                $search[] = $char_to_key[$char_block_12];
                $skip += 11;
            } elseif (array_key_exists($char_block_11, $char_to_key)) {
                $search[] = $char_to_key[$char_block_11];
                $skip += 10;
            } elseif (array_key_exists($char_block_10, $char_to_key)) {
                $search[] = $char_to_key[$char_block_10];
                $skip += 9;
            } elseif (array_key_exists($char_block_9, $char_to_key)) {
                $search[] = $char_to_key[$char_block_9];
                $skip += 8;
            } elseif (array_key_exists($char_block_8, $char_to_key)) {
                $search[] = $char_to_key[$char_block_8];
                $skip += 7;
            } elseif (array_key_exists($char_block_7, $char_to_key)) {
                $search[] = $char_to_key[$char_block_7];
                $skip += 6;
            } elseif (array_key_exists($char_block_6, $char_to_key)) {
                $search[] = $char_to_key[$char_block_6];
                $skip += 5;
            } elseif (array_key_exists($char_block_5, $char_to_key)) {
                $search[] = $char_to_key[$char_block_5];
                $skip += 4;
            } elseif (array_key_exists($char_block_4, $char_to_key)) {
                $search[] = $char_to_key[$char_block_4];
                $skip += 3;
            } elseif (array_key_exists($char_block_3, $char_to_key)) {
                $search[] = $char_to_key[$char_block_3];
                $skip += 2;
            } elseif (array_key_exists($char_block_2, $char_to_key)) {
                $search[] = $char_to_key[$char_block_2];
                $skip++;
            } elseif (array_key_exists($char, $char_to_key)) {
                $search[] = $char_to_key[$char];
            }
        }
        return $search;
    }
}


