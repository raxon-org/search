<?php
namespace Package\Raxon\Search\Trait;

use Exception;
use Raxon\Module\Cli;
use Raxon\Module\Core;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Module\Filter;
use Raxon\Module\Parallel;
use Raxon\Module\Sort;


trait Source {

    public function dictionary_create(object $flags, object $options): void
    {
        $object = $this->object();
        //read code files in /Application (php, js, css, tpl, html)
        //read code files in /mnt/Vps3/Mount/Domain/
        //read code files in /mnt/Vps3/Mount/Shared/
        //read code files in /mnt/Vps3/Mount/Package/

        $options_dir_read = [
            'extension' => [
                'php',
                'js',
                'css',
                'tpl',
                'html',
                'rax'
            ]
        ];
        $url = $object->config('project.dir.root');
        $list = $this->dir_read($url, $options_dir_read);
        $url_dictionary = $object->config('controller.dir.data') . 'Oxford.txt';
        $list_words = explode("\n", File::read($url_dictionary));
        $list_words_application = $this->read_words($list);
        breakpoint(count($list_words_application));
        dd($list_words_application);

        breakpoint(count($list_words));
        dd($list_words);
    }

    public function read_words(array $list): array
    {
        $list_words = [];
        $count_total = count($list);
        $start = microtime(true);
        echo 'Read ' . $count_total . ' files' . PHP_EOL;
        $counter = 0;
        $threads = 8;
        $object = $this->object();
        $chunks = array_chunk($list, $threads);
        $chunk_count = count($chunks);
        $count = 0;
        $done = 0;
        $result = [];
        foreach($chunks as $chunk_nr => $chunk) {
            $closures = [];
            $forks = count($chunk);
            for ($i = 0; $i < $forks; $i++) {
                $closures[] = function () use (
                    $object,
                    $chunk,
                    $chunk_nr,
                    $chunk_count,
                    $i,
                ) {
                    if (array_key_exists($i, $chunk)) {
                        $file = $chunk[$i] ?? false;
                        if($file){
                            $list_words = [];
                            $read = File::read($file->url);
                            $words = explode(' ', $read);
                            foreach($words as $word){
                                if(!in_array($word, $list_words, true)){
                                    $list_words[] = $word;
                                }
                            }
                            return $list_words;
                        }
                    }
                    return null;
                };
            }
            $closures_chunks = array_chunk($closures, 16);
            foreach($closures_chunks as $closures_chunk){
                $list = Parallel::new()->execute($closures_chunk);
                foreach ($list as $key => $item) {
                    if (
                        $item !== null &&
                        $item !== 'progress'
                    ) {
                        if(is_array($item)){
                            foreach($item as $word){
                                if(!in_array($word, $list_words, true)){
                                    $list_words[] = $word;
                                }
                                /*
                                $search = $this->array_binarysearch_record($list_words, $word);
                                if($search === false){
                                    $list_words[] = $word;
                                    asort($list_words, SORT_NATURAL);
                                }
                                */
                            }
                        }
                        $count++;
                        $done++;
                    }
                }
                $percentage = round(($count / $count_total), 2);
                $duration = microtime(true) - $start;
                $eta = 'calculating...';
                if($percentage > 0){
                    $ttl = $duration / $percentage;
                    $eta = $ttl - $duration;
                    $eta = Core::time_format($eta, '');
                }
                $duration = Core::time_format($duration, '');
                echo Cli::tput('cursor.up', 1);
                echo Cli::tput('erase.line');
                echo 'Read ' .  round($percentage * 100, 2) . '% files elapsed: ' . $duration .', E.T.A.:' . $eta . PHP_EOL;
            }
        }
        return $list_words;
        /*






            //use spatie fork
            $file->read = File::read($file->url);
            $words = explode(' ', $file->read);
            foreach($words as $word){
                if(!in_array($word, $list_words, true)){
                    $list_words[] = $word;
                }
            }
            $counter++;
            if($count > 1){
                echo Cli::tput('cursor.up', 1);
                echo Cli::tput('erase.line');
                $duration = microtime(true) - $start;

                $percentage = round(($counter / $count) * 100, 2);
                $percentage_negative = 100 - $percentage;
                echo 'Read ' .  $percentage . '% files elapsed: ' . time_format($duration, '') . PHP_EOL;
            }

        }
        return $list_words;
        */
    }


    public function dir_read(string $url, array $options=[]): array
    {
        $dir = new Dir();
        $files = $dir->read($url, true);
        $list = [];
        if($files){
            foreach ($files as $file){
                if($file->type === File::TYPE){
                    $file->extension = File::extension($file->url);
                    if(in_array($file->extension, $options['extension'], true)){
                        $list[] = $file;
                    }
                }
            }
        }
        return $list;
    }

    public function array_binarysearch_record(array $sorted_array, mixed $target, ?int &$count=0, $operator=Filter::OPERATOR_STRICTLY_EQUAL): false | int
    {
        if(
            $count === 0 ||
            $count === null
        ){
            $count = count($sorted_array);
        }
        $low = 0;
        $high = $count - 1;
        $begin = null;
        $end = null;
        if(
            in_array(
                $operator,
                [
                    Filter::OPERATOR_BETWEEN,
                    Filter::OPERATOR_BETWEEN_EQUALS
                ],
                true
            )
        ){
            if(is_array($target) && count($target) === 2){
                $begin = $target[0];
                $end = $target[1];
            }
            elseif(is_string($target)){
                $explode = explode('..', $target, 2);
                if (array_key_exists(1, $explode)) {
                    if (is_numeric($explode[0])) {
                        $explode[0] += 0;
                    }
                    if (is_numeric($explode[1])) {
                        $explode[1] += 0;
                    }
                    $begin = $explode[0];
                    $end = $explode[1];
                }
            }
        }
        while ($low <= $high) {
            $mid = (int) floor(($low + $high) / 2);
            switch($operator){
                case '===':
                case Filter::OPERATOR_STRICTLY_EXACT:
                case Filter::OPERATOR_STRICTLY_EQUAL:
                    if ($sorted_array[$mid] === $target) {
                        return $mid;
                    } elseif ($sorted_array[$mid] < $target) {
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                    break;
                case '==' :
                case Filter::OPERATOR_EXACT :
                case Filter::OPERATOR_EQUAL :
                    if ($sorted_array[$mid] == $target) {
                        return $mid;
                    } elseif ($sorted_array[$mid] < $target) {
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                    break;
                case '>' :
                case Filter::OPERATOR_GREATER_THAN :
                    //not all records are found
                    if ($sorted_array[$mid] > $target) {
                        return $mid;
                    } elseif ($sorted_array[$mid] < $target) {
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                    break;
                case '>=' :
                case Filter::OPERATOR_GREATER_THAN_EQUAL :
                    //not all records are found
                    if ($sorted_array[$mid] >= $target) {
                        return $mid;
                    } elseif ($sorted_array[$mid] < $target) {
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                    break;
                case '<' :
                case Filter::OPERATOR_LOWER_THAN :
                    //not all records are found
                    if (
                        $sorted_array[$mid] < $target
                    ) {
                        return $mid;
                    } elseif ($sorted_array[$mid] < $target) {
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                    break;
                case '<=' :
                case Filter::OPERATOR_LOWER_THAN_EQUAL :
                    //not all records are found
                    if ($sorted_array[$mid] <= $target) {
                        return $mid;
                    } elseif ($sorted_array[$mid] < $target) {
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                    break;
                case '!=' :
                case Filter::OPERATOR_NOT_EQUAL :
                case Filter::OPERATOR_NOT_EXACT :
                    if ($sorted_array[$mid] != $target) {
                        return $mid;
                    } elseif ($sorted_array[$mid] < $target) {
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                    break;
                case '!==' :
                case Filter::OPERATOR_NOT_STRICTLY_EQUAL :
                case Filter::OPERATOR_NOT_STRICTLY_EXACT :
                    if ($sorted_array[$mid] !== $target) {
                        return $mid;
                    } elseif ($sorted_array[$mid] < $target) {
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                    break;
                case '> <' :
                case Filter::OPERATOR_BETWEEN :
                    if (
                        $sorted_array[$mid] > $begin &&
                        $sorted_array[$mid] < $end
                    ) {
                        return $mid;
                    }
                    elseif ($sorted_array[$mid] <= $begin) {
                        $low = $mid + 1;
                    }
                    elseif ($sorted_array[$mid] >= $end) {
                        $high = $mid - 1;
                    }
                    else {
                        $high = $mid - 1;
                    }
                    break;
                case '>=<' :
                case Filter::OPERATOR_BETWEEN_EQUALS :
                    if (
                        $sorted_array[$mid] >= $begin &&
                        $sorted_array[$mid] <= $end
                    ) {
                        return $mid;
                    }
                    elseif ($sorted_array[$mid] < $begin) {
                        $low = $mid + 1;
                    }
                    elseif ($sorted_array[$mid] > $end) {
                        $high = $mid - 1;
                    }
                    else {
                        $high = $mid - 1;
                    }
                    break;
                case Filter::OPERATOR_STRICTLY_START:
                    if(
                        mb_substr($sorted_array[$mid], 0, mb_strlen($target)) === $target
                    ){
                        return $mid;
                    }
                    elseif (mb_substr($sorted_array[$mid], 0, mb_strlen($target)) < $target) {
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                    break;
                case Filter::OPERATOR_START:
                    if(
                        mb_strtolower(mb_substr($sorted_array[$mid], 0, mb_strlen($target))) === mb_strtolower($target)
                    ){
                        return $mid;
                    }
                    elseif (mb_strtolower(mb_substr($sorted_array[$mid], 0, mb_strlen($target))) < mb_strtolower($target)) {
                        $low = $mid + 1;
                    } else {
                        $high = $mid - 1;
                    }
                    break;
            }
        }
        return false;
    }
}