# Wordpress Upgrade
Upgrades all WordPress installs in a folder via the command line.

## Config
If you install this in your web root in a folder that writable by the user 
you're running it as and you're sure you can upgrade all of your WordPress 
installs in your web root, you probably don't need a config. The script tries 
to detect all the settings.

You'll get a report of the config when you run the script, and you'll have some
time to look at the config so you can abort if you see anything wrong. If you
do, copy the config from the terminal into `config.json`, then edit as you need.

Here's a sample config with the settings that are used.

```
{
	"executables": {
		"mysqldump": "/path/to/mysqldump",
		"mysql": "/path/to/mysql",
		"tar": "/path/to/tar",
		"find": "/path/to/find",
		"curl": "/path/to/curl",
		"du": "/path/to/du",
		"cp": "/path/to/cp"
	},
	"paths": {
		"sites": dirname($script_path) . "/",
		"backups": $script_path . "backups/",
		"wordpress": $script_path . "wordpress/"
	},
	"include": ["/path/site1.com/", "/path/site2.com/"],
	"exclude": ["/path/site3.com/", "/path/site4.com/"]
}

```

You have to use double quotes to make it valid JSON.

## Command Line Parameters
From `upgrade.php --help`:

```
--clean          	Delete any WordPress temp files and download a new copy.
--help           	Show this message.
--list           	Short cut for no download, no backup, no upgrade.
--no-download    	Disable download of new WordPress.
--no-backup      	Do not perform backups of sites.
--no-upgrade     	Do not perform WordPress upgrade.
--package {value}	Specify the full URL to a WordPress gzip.
--single {value} 	Specify a path to a single WordPress install to upgrade.
--version {value}	Override lates version number. For example: 4.9.4
```
