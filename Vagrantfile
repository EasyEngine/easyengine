# -*- mode: ruby -*-
# vi: set ft=ruby :
VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
    config.vm.synced_folder "easyengine/", "/opt/easyengine"
    config.vm.provision "shell", inline: <<-SHELL
      apt-get install -qqy git
      pushd /opt/easyengine > /dev/null
      git checkout feature/v4.0.0
      /bin/bash install feature/v4.0.0
      source config/bash_completion.d/ee_auto.rc
      popd > /dev/null
    SHELL

    ## Ubuntu 12.04 64 bit
    config.vm.define "precise64" do |precise64|
      precise64.vm.provider "virtualbox" do |v|
          v.memory = 1024
      end
      precise64.vm.network "forwarded_port", guest: 80, host: 8881
      precise64.vm.box = "ubuntu/precise64"
      precise64.vm.network "private_network", ip: "192.168.33.12"
      precise64.ssh.forward_agent = true
    end

    # Ubuntu 12.04 32 bit
    config.vm.define "precise32" do |precise32|
      precise32.vm.provider "virtualbox" do |v|
          v.memory = 1024
      end
      precise32.vm.box = "ubuntu/precise32"
      precise32.vm.network "forwarded_port", guest: 80, host: 8882
      precise32.vm.network "private_network", ip: "192.168.33.13"
      precise32.ssh.forward_agent = true
    end

    # Ubuntu 14.04 64 bit
    config.vm.define "trusty64" do |trusty|
      trusty.vm.provider "virtualbox" do |v|
          v.memory = 1024
      end
      trusty.vm.box = "ubuntu/trusty64"
      trusty.vm.network "forwarded_port", guest: 80, host: 8883
      trusty.vm.network "private_network", ip: "192.168.33.14"
      trusty.ssh.forward_agent = true
    end

    # Ubuntu 14.04 32 bit
    config.vm.define "trusty32" do |trusty32|
      trusty32.vm.provider "virtualbox" do |v|
          v.memory = 1024
      end
      trusty32.vm.box = "ubuntu/trusty32"
      trusty32.vm.network "forwarded_port", guest: 80, host: 8884
      trusty32.vm.network "private_network", ip: "192.168.33.15"
      trusty32.ssh.forward_agent = true
    end

    #Debian 8 64 bit
    config.vm.define "jessie64" do |jessie64|
      jessie64.vm.provider "virtualbox" do |v|
          v.memory = 1024
      end
      jessie64.vm.box = "debian/jessie64"
      jessie64.ssh.forward_agent = true
      jessie64.vm.network "forwarded_port", guest: 80, host: 8885
      jessie64.vm.network "private_network", ip: "192.168.35.18"
    end
    #Debian 8 32 bit
    config.vm.define "jessie32" do |jessie32|
      jessie32.vm.provider "virtualbox" do |v|
          v.memory = 1024
      end
      jessie32.vm.box = "boxcutter/debian8-i386"
      jessie32.ssh.forward_agent = true
      jessie32.vm.network "forwarded_port", guest: 80, host: 8886
      jessie32.vm.network "private_network", ip: "192.168.33.19"
    end

    #Ubuntu 16.04 64 bit
    config.vm.define "xenial64" do |xenial64|
      xenial64.vm.provider "virtualbox" do |v|
          v.memory = 1024
      end
      xenial64.vm.box = "ubuntu/xenial64"
      xenial64.ssh.forward_agent = true
      xenial64.vm.network "forwarded_port", guest: 80, host: 8887
      xenial64.vm.network "private_network", ip: "192.168.33.20"
    end

    #Ubuntu 16.04 32 bit
    config.vm.define "xenial32" do |xenial64|
      xenial64.vm.provider "virtualbox" do |v|
          v.memory = 1024
      end
      xenial64.vm.box = "ubuntu/xenial32"
      xenial64.ssh.forward_agent = true
      xenial64.vm.network "forwarded_port", guest: 80, host: 8888
      xenial64.vm.network "private_network", ip: "192.168.33.21"
    end
end
