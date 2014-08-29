# The Freezer

Simple directory backup and restore utility.

## Installation

Add a line to your "require" section in your composer configuration:

```
"require": {
    "dreamfactory/freezer": "*"
}
```

Run a composer update:

```shell
$ composer update
```

## Usage
This package contains useful classes for directory backup and restore. Also included is a command line utility to perform the same functions from scripts.

The utility is called **freezer** and is located at `bin/freezer` of this repo.

The freezer takes a directory and creates an archive named by you. You may choose to compress with tar, zip, or gzip. You may also have freezer store the archive in a database of your choosing.

### Command Line

Basic usage:

```
$ bin/freezer freeze:[path|db] [options] /path/to/archive /path/to/save/archive/file.zip
```

By default, the freezer's `freeze` command defaults to `path` mode. You may specify the `freeze:db` command to store the archive in the database instead. When using `freeze:db`, you must specify additional database credentials. They are:

| Short | Long | Description | Default Value |
|-------|------|-------------|---------------|
| -h | --host *db_host* | The database host name | localhost |
| -n | --name *db_name* | The database name | dreamfactory |
| -u | --username *db_username* | The database user | dsp_user |
| -p | --password *db_password* | The database password | dsp_user |
| n/a | --port *db_port* | The database port | 3306 |

The following options are available for all freeze/defrost commands:

| Short | Long | Description | Default Value |
|-------|------|-------------|---------------|
| -z | --zip | Make a **zip** archive | true |
| -t | --tar | Make a **tar** archive | false |
| -x | --tgz | Make a **tar.gz** archive | false |
| -g | --gzip | Make a **gzip** archive | false |
| -h | --help | Program usage and help | N/A |
| -q | --quiet | Only display errors | N/A |
| -v | --verbose | Be more chatty | N/A |

#### Examples

To create a Freezer archive file:

```
$ bin/freezer freeze -x /home/code_ninja/projects /home/code_ninja/backups/projects.tar.gz
frozen: "/home/code_ninja/projects" archived to "/home/code_ninja/backups/projects.tar.gz"
```

To restore a Freezer archive:

```
$ bin/freezer defrost -q /home/code_ninja/backups/projects.tar.gz /home/code_ninja/projects
```
