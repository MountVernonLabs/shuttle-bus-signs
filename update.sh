#!/bin/bash
cd ~/Projects/shuttle-bus-signs/
for i in 1 2 3 4 5 6
do
  php dg.php
  php wharf.php
  sleep 8
done
