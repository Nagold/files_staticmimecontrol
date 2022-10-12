# Files Staticmimecontrol

This Files Staticmimecontrol app for Nextcloud enables administrators to whitelist specific mime types per folder with matching from a static rule file. This App is based on the work of [files_accesscontrol](https://github.com/nextcloud/files_accesscontrol)

# Sample Config

The configuration gets loaded from `data/staticmimecontrol.json` by default. You can set a custom path in your config.php with the config parameter `staticmimecontrol_file`.

an example config looks like that:

```
{
    "denyrootbydefault": true,
    "rules": [
        {
            "path": "asd",
            "mime": "image/jpeg"
        },
        {
            "path": "asd",
            "mime": "image/png"
        }
    ]
}
```

# Development

## initial setup

To setup a new development instance, we recommend to use juliushaertl/nextcloud-docker-dev. Prerequired are Docker and docker-compose and Make.

```
sudo echo "127.0.0.1 nextcloud.local" >> /etc/hosts
sudo echo "127.0.0.1 stable24.local" >> /etc/hosts

mkdir -p $HOME/temp_staticmimecontrol
git clone https://github.com/juliushaertl/nextcloud-docker-dev $HOME/temp_staticmimecontrol/nextcloud-docker-dev
cd $HOME/temp_staticmimecontrol/nextcloud-docker-dev
./bootstrap.sh
cd $HOME/temp_staticmimecontrol/nextcloud-docker-dev/workspace/server/
git fetch --unshallow
git config remote.origin.fetch "+refs/heads/*:refs/remotes/origin/*"
git fetch origin
git worktree add ../stable24 stable24
cd ../stable24
git submodule update --init
git clone https://github.com/Nagold/files_staticmimecontrol $HOME/temp_staticmimecontrol/nextcloud-docker-dev/workspace/stable24/apps/files_staticmimecontrol
cd $HOME/temp_staticmimecontrol/nextcloud-docker-dev/workspace/stable24/apps/files_staticmimecontrol
make composer-install
cd $HOME/temp_staticmimecontrol/nextcloud-docker-dev
docker-compose up -d stable24 proxy database-mysql
```

now your test instance is running.

## Stop dev env

```
cd $HOME/temp_staticmimecontrol/nextcloud-docker-dev && docker-compose down
```

## run dev env afterwards

```
cd $HOME/temp_staticmimecontrol/nextcloud-docker-dev && docker-compose up -d stable24 proxy database-mysql && docker-compose logs -f
```

## follow container logs

```
cd $HOME/temp_staticmimecontrol/nextcloud-docker-dev && docker-compose logs -f
```

## Configure VSCode and Chrome/Brave

* Create the file $HOME/temp_staticmimecontrol/nextcloud-docker-dev/workspace/stable24/.vscode/launch.json with the following contents, to enable xdebug:

```
{
    // Use IntelliSense to learn about possible attributes.
    // Hover to view descriptions of existing attributes.
    // For more information, visit: https://go.microsoft.com/fwlink/?linkid=830387
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "hostname": "0.0.0.0",
            "pathMappings": {
                "/var/www/html/": "${workspaceFolder}"
            },
			"ignore": [
                "**/lib/private/AppFramework/Utility/SimpleContainer.php",
                "**/lib/private/ServerContainer.php",
				"**/lib/private/AppFramework/DependencyInjection/DIContainer.php",
				"**/lib/private/App/AppManager.php",
				"**/lib/public/AppFramework/Db/QBMapper.php",
				"**/lib/private/Share20/ProviderFactory.php",
				"**/3rdparty/symfony/routing/Matcher/UrlMatcher.php",
				"**/lib/private/Route/Router.php",
				"**/lib/private/Files/Node/Root.php",
				"**/lib/private/Files/AppData/AppData.php",
				"**/apps/files/lib/Activity/Helper.php",
				"**/lib/private/Files/Template/TemplateManager.php",
				"**/lib/private/Share20/Manager.php"
            ]
        }
    ]
}

``` 

* Install the Chrome Extension https://chrome.google.com/webstore/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc/related
* Install vscode Extension https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug
* pin xdebug helper to your Browser bar
* go to http://stable24.local/index.php/login and enable debugging via the xdebug helper button
* Open $HOME/temp_staticmimecontrol/nextcloud-docker-dev/workspace/stable24 in vscode and press F5 to run xdebug debugging
* refresh http://stable24.local/index.php/login , login with admin:admin and enable files_staticmimecontrol via the admin menu
* happy debugging :D

## how to edit staticmimecontrol.json in the container

```
cd $HOME/temp_staticmimecontrol/nextcloud-docker-dev && docker-compose exec stable24 /bin/bash
rm -rf /etc/apt/sources.list.d/blackfire.list && apt-get update && apt-get install nano
nano data/staticmimecontrol.json
```



# Todo

* Tests
* publish to appstore
