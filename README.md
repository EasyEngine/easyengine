
How To setup this version on your system??

```bash
sudo apt-get install python3-pip git
sudo pip3 install virtualenv
git clone -b python https://github.com/rtCamp/easyengine.git
cd easyengine
virtualenv ./env  --system-site-packages
source ./env/bin/activate
sudo pip3 install -r requirements.txt
sudo python3 setup.py develop
ee --help
```

How to install this version on your system??
```bash
sudo apt-get update
sudo apt-get install python3 python3-apt python3-setuptools python3-dev git
git clone -b python https://github.com/rtCamp/easyengine.git
cd easyengine
sudo python3 setup.py install
ee --help
```


EasyEngine 3.x Developement version


Thinking To Contribute???

refer docs to know more on EasyEngine Developement

follow instruction from step 3 in Creating cement app
http://builtoncement.com/2.4/dev/boss_templates.html
