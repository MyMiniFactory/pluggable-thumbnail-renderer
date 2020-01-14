<?php

// Reading command arguments
$OPTIONS = getopt("f:i:o:s:", ["filename:","input:","output:","status:"]);

$FILENAMEARG = (array_key_exists("f", $OPTIONS) ? $OPTIONS['f'] : $OPTIONS['filename']) ;
$INPUTARG = (array_key_exists("i", $OPTIONS) ? $OPTIONS['i'] : $OPTIONS['input']);
$OUTPUTARG = (array_key_exists("o", $OPTIONS) ? $OPTIONS['o'] : $OPTIONS['output']);
$STATUSARG = (array_key_exists("s", $OPTIONS) ? $OPTIONS['s'] : $OPTIONS['status']);

echo($FILENAMEARG . PHP_EOL);
echo($INPUTARG . PHP_EOL);
echo($OUTPUTARG . PHP_EOL);
echo($STATUSARG . PHP_EOL);

// Creating folders
if(!is_dir($INPUTARG)) {
  mkdir($INPUTARG);
}
if(!is_dir($OUTPUTARG)) {
  mkdir($OUTPUTARG);
}


$filesInInput = array_slice(scandir($INPUTARG), 2);

$filesToProcess = [
    [
        "objectName" => $FILENAMEARG, 
        "objectPath" => $INPUTARG . '/' . $filesInInput[0]
    ]
];

$statusJson = [];
$fp = fopen($STATUSARG.'/status.json', 'w');
fwrite($fp, json_encode($statusJson));
fclose($fp);
$metadataJson = [];

// Processing each file
foreach ($filesToProcess as $file) {
  $time_start = microtime(true); 

    // Conversion to stl if its a obj
    if(file_exists($file["objectPath"])) {
        $file_extension = pathinfo($file["objectPath"], PATHINFO_EXTENSION);

        // Checking validity of file for stl2pov
        if(file_exists($file["objectPath"]) &&  strtolower($file_extension) != "stl"){
          echo(PHP_EOL."Converting file to stl".PHP_EOL);
            $stlPath = str_replace($file_extension, 'stl', $file["objectPath"]);
            exec("ctmconv ".$file["objectPath"]." ".$stlPath);
            if(file_exists($stlPath)){
              $file["objectPath"] = str_replace($file_extension, "stl", $file["objectPath"]);
            } else {
              echo("Error unsuported file type for rendering");
              $fp = fopen('/app/files/results.json', 'w');
              fwrite($fp, json_encode($statusJson));
              fclose($fp);
              return 0;
            }
        }
    } else {
        echo("Error file".$file["objectName"]." not found");
        $fp = fopen('/app/files/results.json', 'w');
              fwrite($fp, json_encode($statusJson));
              fclose($fp);
              return 0;
    }

    echo($file["objectPath"]);

    // Stl simplification under threshold
    $treshold = 5242880;
    $filesize = filesize($file["objectPath"]);
    echo(PHP_EOL."old size : ".$filesize.PHP_EOL);
    if ($filesize > $treshold) {
      $path = "tmp/".$file["objectName"]."-simplified.stl";
      $percentageDecrease = 1 - round(($filesize - $treshold)/$filesize, 2, PHP_ROUND_HALF_DOWN);
      echo("Percentage to decrease: ".$percentageDecrease.PHP_EOL);
      exec("/app/a.out ".$file["objectPath"]." ".$path." ".$percentageDecrease);
      
      if (file_exists($path)){

        rename($path, $file["objectPath"]);

        echo("new size : ".filesize($file["objectPath"]).PHP_EOL);
      } else {
        echo("Error while simplifying file");
        $fp = fopen('/app/files/results.json', 'w');
              fwrite($fp, json_encode($statusJson));
              fclose($fp);
              return 0;
      }
    };

    // Conversion to .pov with stl2pov
    exec('/app/stl2pov '.$file["objectPath"].' > tmp/thumbnail-'.$file["objectName"].'.pov');
    if(!file_exists('tmp/thumbnail-'.$file["objectName"].'.pov')) {
        echo("Error reading the file data content");

        // Writting the error on the status file
        array_push($statusJson, [
          "stl2pov conversion" => [
            "status" => "error",
            "progress" => "100%"
          ]
        ]);

        $fp = fopen($STATUSARG.'/status.json', 'w');
        fwrite($fp, json_encode($statusJson));
        fclose($fp);

        $fp = fopen('/app/files/results.json', 'w');
              fwrite($fp, json_encode($statusJson));
              fclose($fp);
              return 0;
    } else {
        // Editing the status file
        array_push($statusJson, [
          "stl2pov conversion" => [
            "status" => "done",
            "progress" => "100%"
          ]
        ]);
        $fp = fopen($STATUSARG.'/status.json', 'w');
        fwrite($fp, json_encode($statusJson));
        fclose($fp);

        // Reading the pov file
        $fileName = 'tmp/thumbnail-'.$file["objectName"].'.pov';

        // Preparing the pov file for the render
        // the name of the mesh to correspond to the template
        $reading = fopen($fileName, 'r');
        $writing = fopen($file["objectName"].'tmp', 'w');
        
        $replaced = false;
        
        // Replacing the name of the mesh to correspond to the template
        while (!feof($reading)) {
          $line = fgets($reading);
          if (stristr($line,'mesh {')) {
            $line = "#declare m_body= mesh {";
            $replaced = true;
          }
          fputs($writing, $line);
        }
        
        fclose($reading); fclose($writing);

        if ($replaced) 
        {
          rename($file["objectName"].'tmp', $fileName);
        } else {
          unlink($file["objectName"].'tmp');
        }

        // Reading the templates files
        $template = file_get_contents('template.pov', true);

        // Adding the template to the pov files
        file_put_contents($fileName, $template, FILE_APPEND);

        // Generating the frames
        $Width = 300;
        $Height = 300;

        $outputfilepath = $OUTPUTARG.'/';

        $command = 'povray "'.'tmp/thumbnail-'.$file["objectName"].'.pov'.'" +FN +W'.$Width.' +H'.$Height.' -O'.$outputfilepath.' +Q9 +AM1 +A +UA -D';

        // Executing Povray
        exec($command);

        // Checking if the thumbnail is generated.
        if(!file_exists($outputfilepath.'thumbnail-'.$file["objectName"].'.png')){
          echo("Error when generating the thumbnail");

          // Writting the error on the status file
          array_push($statusJson, [
            "Povray rendering" => [
              "status" => "error",
              "progress" => "100%"
            ]
          ]);

          $fp = fopen($STATUSARG.'/status.json', 'w');
          fwrite($fp, json_encode($statusJson));
          fclose($fp);

          $fp = fopen('/app/files/results.json', 'w');
          fwrite($fp, json_encode($statusJson));
          fclose($fp);
          return 0;
        } else {
          array_push($statusJson, [
            "Povray rendering" => [
              "status" => "done",
              "progress" => "100%"
            ]
          ]);

          $fp = fopen($STATUSARG.'/status.json', 'w');
          fwrite($fp, json_encode($statusJson));
          fclose($fp);
        }
      }  
    }

// Clearing the tmp folder recursively
$dir = 'tmp';
$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

foreach($files as $file) {

    if ($file->isDir()){
        rmdir($file->getRealPath());
    } else {
        unlink($file->getRealPath());
    }

}
$time_end = microtime(true);

$execution_time = ($time_end - $time_start)/60;

array_push($statusJson, [
  "processing" => [
    "status" => "done",
    "progress" => "100%",
    "execution_time" => $execution_time
  ]
]);

// Writting the status file
$fp = fopen($STATUSARG.'/status.json', 'w');
fwrite($fp, json_encode($statusJson));
fclose($fp);

return 0;

?>
