<?php
namespace Package\Raxon\Search\Trait;

use Composer\Advisory\PartialSecurityAdvisory;
use DOMDocument;
use DOMXPath;
use Raxon\Config;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Cli;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Exception;
use Raxon\Module\Time;

trait Raxon {
    const VERSION = '1.0.0';

    /**
     * @throws Exception
     */
    public function generate(object $flags, object $options): void
    {
        $object = $this->object();
        Core::interactive();
        echo 'Initializing...' . PHP_EOL;
        $dir_documentation = $object->config('project.dir.data') . 'Documentation' . $object->config('ds');
        $dir_source = $dir_documentation . 'Source' . $object->config('ds');
        $target_raxon = $object->config('project.dir.domain') . 'Www.Raxon.Org/Public/Source/';
        File::permission($object, ['dir_docs' => $dir_documentation, 'dir_source' => $dir_source, 'target_raxon' => $target_raxon]);
        Dir::create($target_raxon, Dir::CHMOD);
        $dir = new Dir();

        $read = $dir->read('/Application/', true);
        if($read){
            foreach($read as $file){
                if($file->type === File::TYPE){
                    $file->extension = File::extension($file->url);
                    if(
                        in_array(
                            $file->extension,
                            [
                                'html',
                                'css',
                                'js',
                                'tpl',
                                'php'
                            ],
                            true
                        )
                    ){
                        $this->create_page_html($file, [
                            'target' => $target_raxon
                        ]);
                    }
                }
            }
        }


        /*
        $read = $dir->read($dir_source);
        $chunkSize = 8192 * 128; // 1 MB (max page size is 1 MB)
        $counter = 0;
        $total = 0;
        foreach($read as $nr => $file){
            $handle = fopen($file->url, 'rb');
            if ($handle === false) {
                die("Unable to open file.");
            }
            $size = File::size($file->url);
            $data = [];
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                // Do something with $chunk
                $data[] = $chunk; // or process/save it
                $counter++;
                if($counter >= 32){  //32 MB at a time...
                    $string = implode('', $data);
                    $pages = $this->extract_pages($string);
                    $this->store_pages($pages, $target_wiki_en);
                    $data = [];
                    $data[] = $chunk; //maybe incomplete the last block so we use it again...
                    $block_size = $chunkSize * $counter;
                    $total += $block_size;
                    $duration = microtime(true) - $object->config('time.start');
                    $duration_percentage = round($duration * ($block_size / $total), 3);
                    $time_remaining = round($duration_percentage - $duration, 3);
                    echo Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Percentage: ~' . round(($total / $size) * 100, 2) . '%; time elapsed: ' . Time::format(round($duration, 2), '', true) . '; time remaining: ' . Time::format(round($time_remaining, 2), '', true) . ';' . PHP_EOL;
                    $counter = 0;
                }
            }
            fclose($handle);
        }
        ddd($read);
        */

        /*


        if(!property_exists($options, 'source')){
            $options->source = $object->config('project.dir.domain') . 'Www.Raxon.Org/Public/php_manual_en/';
        }
        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $dir = new Dir();
        $read = $dir->read($options->source);
        $partition = Core::array_partition($read, 25);
        $total = count($partition);
        $count = 0;
        foreach($partition as $nr => $chunk){
            $import=[];
            foreach($chunk as $file){
                $import[] = '-url[]=https://raxon.local/php_manual_en/' . $file->name;
            }
            $count++;
            $command = Core::binary($object) . ' raxon/search import page ' . implode(' ', $import) . ' -version='. $options->version;
            $output = shell_exec($command);
            echo $output . PHP_EOL;
            $time = microtime(true);
            $duration = round($time - $object->config('time.start'), 3);
            $duration_percentage = round($duration / ($count / $total), 3);
            $duration_left = round($duration_percentage - $duration, 3);
            echo 'Percentage: ' . round(($count / $total) * 100, 2) . '% duration: ' . $duration . '; total duration: ' . $duration_percentage . '; time left: ' . $duration_left  . '; memory: ' . File::size_format(memory_get_peak_usage(true)) . PHP_EOL;
        }
        */
    }

    /**
     * @throws FileWriteException
     * @throws ObjectException
     */
    private function create_page_html(object $file, array $options=[]){
        $object = $this->object();
        $read = File::read($file->url);
        $hash = hash('sha256', $file->url);
        $target = $options['target'] . $hash . $object->config('extension.html');
        $html = [];
        $html[] = '<html>';
        $html[] = '<head>';
        $html[] = '<title>' . $file->url .'</title>';
        $html[] = '</head>';
        $html[] = '<body>';
        $html[] = '<h1>' . $file->url . '</h1>';
        $html[] = '<pre>' . htmlspecialchars($read) . '</pre>';
        $html[] = '</body>';
        $html[] = '</html>';
        File::write($target, implode(PHP_EOL, $html));


        d($file->url);
        breakpoint($target);
        ddd($read);
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function import(object $flags, object $options): void
    {
        $object = $this->object();
        echo 'Initializing...' . PHP_EOL;
        Core::interactive();
        if(!property_exists($options, 'source')){
            $options->source = $object->config('project.dir.domain') . 'Www.Raxon.Org/Public/Source/';
        }
        if(!property_exists($options, 'version')){
            $options->version = self::VERSION;
        }
        if(!property_exists($options, 'target')){
            $dir_list =
                $object->config('ramdisk.url') .
                $object->config(Config::POSIX_ID) .
                $object->config('ds') .
                'Search' .
                $object->config('ds')
            ;
        } else {
            $dir_list = $options->target;
            if(substr($dir_list, -1) !== $object->config('ds')){
                $dir_list .= $object->config('ds');
            }
            Dir::create($dir_list, Dir::CHMOD);
            File::permission($object, ['dir_list' => $dir_list]);
        }

        $dir_data = $object->config('controller.dir.data');
        $dir_search = $dir_data . 'Search' . $object->config('ds');
        $dir_version = $dir_search . $options->version . $object->config('ds');
        $source = $dir_version . 'Search' . $object->config('extension.json');
        $dir = new Dir();
        $read = $dir->read($options->source);
        $partition = Core::array_partition($read, 500); // (100 * 1 GB ? )
        $total = count($partition);
        $count = 0;
        foreach($partition as $nr => $chunk){
            $import=[];
            $list = [];
            foreach($chunk as $file){
                $list[] = 'https://raxon.local/Source/' . $file->name;
//                $import[] = '-url[]=https://raxon.local/wiki/en/' . $file->name;
            }
            $url_list = $dir_list . $nr . $object->config('extension.json');
            $data = new Data($list);
            $data->write($url_list);
            File::permission($object, ['url_list' => $url_list]);
            $count++;
            /*
            $command = Core::binary($object) . ' raxon/search import page -list=' . $url_list . ' -version='. $options->version;
            $output = shell_exec($command);
            echo $output . PHP_EOL;
            */
            $time = microtime(true);
            $duration = round($time - $object->config('time.start'), 3);
            $duration_percentage = round($duration / ($count / $total), 3);
            $duration_left = round($duration_percentage - $duration, 3);
            echo Cli::tput('cursor.up') . Cli::tput('erase.line') . 'Percentage: ' . round(($count / $total) * 100, 2) . '% duration: ' . Time::format($duration, '', true) . '; total duration: ' . Time::format($duration_percentage, '', true) . '; time left: ' . Time::format($duration_left, '', true)  . '; memory: ' . File::size_format(memory_get_peak_usage(true)) . PHP_EOL;
        }
    }

}

