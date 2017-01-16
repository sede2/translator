<?php

namespace Sede2\Translator;

use Sede2\Excel\ExcelCreator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

class Translator
{
    public static function getLang($lang = 'en')
    {
        $finder = new Finder();
        $finder->files()->in(resource_path('lang/' . $lang));

        $translator = [];

        foreach($finder as $file){
            $translator[$file->getFilename()] = include($file->getRealPath());
        }

        $trans = [];

        foreach($translator as $filename => $filecontents){
            $fn = explode('.php', $filename)[0];
            foreach($filecontents as $key => $value){
                if(gettype($value) == 'array') {
                    $t = (new static)->translateFILE($fn . '.' . $key, $value);
                    if($t) {
                        foreach($t as $tvalue){
                            if(! array_key_exists('key', $tvalue)){
                                dd($t, $tvalue);
                            }
                            $trans[$tvalue['key']] = $tvalue['value'];
                        }
                    }
                }else{
                    $trans[$fn . '.' . $key] = $value;
                }
            }
        }
        $aux = [];
        foreach($trans as $key => $value){
            $aux[] = [$key, $value];
        }
        return ExcelCreator::create($aux);
    }

    protected function translateFILE($key, $value){
        $ret = [];
        foreach($value as $k => $v){
            if(gettype($v) == 'array'){
                $aux = $ret;
                $ret = (new static)->translateFILE($key . '.' . $k, $v);
                foreach($aux as $auxvalue){
                    $ret[] = $auxvalue;
                }
            }else{
                $ret[] = ['key' => $key . '.' . $k, 'value' => $v];
            }
        }
        return $ret;
    }

    public static function generateZip($lang = 'es')
    {
        $trans = include(storage_path() . '/app/trans.php');

        $files = [];

        foreach($trans as $key => $value){
            $filename = explode('.', $key)[0];
            $tkey = explode($filename . '.', $key)[1];


            $files[$filename][$tkey] = $value;
        }

        return redirect()->to((new static)->createTransFiles($lang, $files));
    }

    protected function createTransFiles($lang, $files){
        $time = time();
        $tmp = 'lang-' . $time . '/';
        foreach($files as $filename => $filecontent) {
            $val = '';
            foreach($filecontent as $key => $value) {
                $aux = str_replace('\'', '\\\'', $value);
                $val .= "\t'{$key}' => '{$aux}',\n";
            }
            $content = "<?php return [ \n {$val} ];";
            Storage::put($tmp . $lang . '/' . $filename . '.php', $content);
        }
        $exec = 'cd ' . storage_path() . '/app' . '; zip -r ' . public_path() . '/lang-' . $time . '.zip ' . $tmp;
        shell_exec($exec);
        return '/lang-' . $time . '.zip';
    }
}