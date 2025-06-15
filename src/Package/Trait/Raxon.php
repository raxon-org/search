<?php
namespace Package\Raxon\Search\Trait;

use Composer\Advisory\PartialSecurityAdvisory;
use DOMDocument;
use DOMXPath;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Raxon\Config;
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
     * @throws GuzzleException
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

    private function create_page(object $file, array $options=[]){
        $read = File::read($file->url);
        breakpoint($options);
        ddd($read);
    }


    private function store_pages($pages=[], $target_dir=''): void
    {
        $object = $this->object();
        foreach ($pages as $page) {
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadXML($page);
            libxml_clear_errors();

            // Get plain text content
            $title = $doc->getElementsByTagName('title')->item(0) ?? null;
            $text = $doc->getElementsByTagName('text')->item(0) ?? null;

            if($title && $text){
                $title_text = $title->textContent;
                $plain_text = $text->textContent;
                $html = [];
                $html[] = '<html>';
                $html[] = '<head>';
                $html[] = '<title>' . $title_text .'</title>';
                $html[] = '</head>';
                $html[] = '<body>';
                $html[] = '<h1>' . $title_text . '</h1>';
                $html[] = '<p>' . $plain_text . '</p>';
                $html[] = '</body>';
                $html[] = '</html>';
                $target_url = $target_dir . hash('sha256', $plain_text) . $object->config('extension.html');
                if(!File::exist($target_url)){
                    File::write($target_url, implode(PHP_EOL, $html));
                    File::permission($object, ['url' => $target_url]);
                }
            }
        }
    }

    private function extract_pages($string=''): array
    {
        $pages = [];
        $explode = explode('</page>', $string);
        foreach($explode as $nr => $part){
            $temp = explode('<page>', $part, 2);
            if(array_key_exists(1, $temp)){
                $page = '<page>' . str_replace(
                    [
                        "\r\n",
                        "\n\r",
                        "\r",
                    ],
                    [
                        "\n",
                        "\n",
                        "\n"
                    ],
                    $temp[1]
                ) . PHP_EOL . '</page>';
                $pages[] = $page;
            }
        }
        return $pages;
    }
}

