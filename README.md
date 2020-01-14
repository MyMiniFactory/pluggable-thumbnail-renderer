# Thumbnail Renderer

[![Docker Cloud Build Status](https://img.shields.io/docker/cloud/build/myminifactory/pluggable-thumbnail-renderer)](https://hub.docker.com/r/myminifactory/pluggable-thumbnail-renderer)

Thumbnail Renderer is a docker container to generate a thumbnail from a 3D file.
It uses :
- [OpenCTM](http://openctm.sourceforge.net/?page=about) to convert the 3D file to stl.
- [stl2pov](https://github.com/timschmidt/stl2pov) for the conversion from stl to .pov format supported by `Pov-Ray`.
- [Pov-Ray](https://github.com/POV-Ray/povray) to render thumbnail.

example generated :

![thumbnail](https://i.imgur.com/NZLs7Oq.png)

## Build the container
```shell
docker build -t tag_name -f dockerfile_path path_to_the_folder_containing_the_docker_file
```
The -f tag is optional on LINUX if the `Dockerfile` is properly named (no file extension).

## Run the container
```shell
docker run image_name -f|--filename name_of_the_file_without_extension -i|--input input_dir -o|--output output_dir -s|--status path_to_the_folder_where_to_write_the_status_json_file
```

|                         parameter                        |                  example values               |
|----------------------------------------------------------|:---------------------------------------------:|
|           `name_of_the_file_without_extension`           |                    my3dfile                   |
|                        `input_dir`                       |                /app/files/input/              |
|                        `output_dir`                      |                /app/files/output/             |
| `path_to_the_folder_where_to_write_the_status_json_file` |               /app/files/output/              |

Parameters :
- `name_of_the_file_without_extension` : the name of the 3D file without the extension.
- `input_dir`: the relative or absolute path to the folder which contains the 3D file.
- `output_dir`: the relative or absolute path to the folder where the outputs folder will be stacked. Note that the path must exist. 
- `path_to_the_folder_where_to_write_the_status_json_file`: the relative or absolute path to the folder which will contain the status.json file which consists in a live report of the process to use with the task actionner.
