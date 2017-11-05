#!/bin/sh
FNAME=`cat`;
AV_LOG_FORCE_COLOR=1
ffmpeg -i /files/video/${FNAME} -hide_banner -loglevel 24 -vn -acodec copy /files/audio/${FNAME}.m4a
