var stray={
    'start': function() {
        $.ajaxSettings.timeout="30000"; 
        $.ajaxSettings.async = true;
        $.post("api.php", {"url":config.url,"time":config.time,"token":config.token},
        function(data) {
            if(data.code=="200"){
                stray.url=data.url;
                stray.type=stray.videotype(data.url,data.type);
                $("#loading").remove();
                $("body").append("<div id=\"artplayer\" class=\"artplayer-app\" style=\"width:100%;height:100%;padding:0;margin:0\"></div>");
                if(config.danmuqidong==1){
                    stray.DmPlayer();
                }else{
                    stray.ArtPlayer();
                }
            }else{
                TheError();
            }
        },'json').error(function (xhr, status, info) {
            TheError();
        });
    },
    'ArtPlayer':function(){
        stray.ad = new Artplayer({
            container: '#artplayer',  /*播放器ID*/
            theme: config.themeColor,  /*进度条颜色*/
            url: decrypt(stray.url), /*视频播放地址*/
            airplay: true,
            type:stray.type,
            title: config.title,   /*视频标题，目前会出现在视频截图和迷你模式下*/
            poster: config.background, /*视频的海报*/
            volume: 1, /*声音默认1*/
            muted: false, /*是否默认静音*/
            autoplay: true, /*否自动播放*/
            flip: true,/*是否视频翻转*/
            pip: true, /*是否显示画中画按钮*/
            autoSize: false,  /*自动调整播放器尺寸以隐藏黑边*/
            autoMini: true,  /*当播放器滚动到浏览器视口以外时，自动进入迷你播放模式*/
            //screenshot: true,  /*显示视频截图功能*/
            setting: true,  /*显示设置面板的开关按钮*/
            loop: false,  /*是否循环播放 默认: false*/
            lock: true,/*是否在移动端显示一个 锁定按钮 ，用于隐藏底部 控制栏*/
            playbackRate: true,  /*是否显示视频播放速度功能，会出现在 设置面板 和 右键菜单 里*/
            aspectRatio: true,  /*是否显示视频长宽比功能，会出现在 设置面板 和 右键菜单 里*/
            fullscreen: true,  /*是否在底部控制栏里显示播放器窗口全屏按钮*/
            //fullscreenWeb: true,  /*是否在底部控制栏里显示播放器网页全屏按钮*/
            miniProgressBar: true,  /*迷你进度条，只在播放器失去焦点后且正在播放时出现*/
            hotkey: true, /*是否启用快捷键*/
            backdrop: true,  
            fastForward: true,/*是否在移动端添加长按视频快进功能*/
            playsInline: true,  /*移动端是否使用 playsInline 模式*/
            autoOrientation: true,/*是否在移动端的网页全屏时，根据视频尺寸和视口尺寸，旋转播放器*/
            lang: navigator.language.toLowerCase(),
            whitelist: ['*'],
/*            contextmenu: [
                {
                    html: config.contextmenu,
                    click: function () {
                        window.open(config.contextlink);
                    },
                },
            ],
            moreVideoAttr: {
                crossOrigin: 'anonymous',
            },*/
            icons: {loading: '<img src="artplayer/img/ploading.gif">',state: '<img width="150" heigth="150" src="artplayer/img/state.svg">',indicator: '<img width="16" heigth="16" src="artplayer/img/indicator.svg">',},
            customType: {m3u8: playM3u8, flv: playFlv, ts: playTs, mpd: playMpd,},
        });
        stray.load();
    },
    'DmPlayer': function() {
        stray.danmuapi=config.api + '&url=';
        stray.danmuapisend=config.api + config.url;
        stray.ad = new Artplayer({
            container: '#artplayer',  /*播放器ID*/
            theme: config.themeColor,  /*进度条颜色*/
            url: decrypt(stray.url), /*视频播放地址*/
            airplay: true,
            type:stray.type,
            title: config.title,   /*视频标题，目前会出现在视频截图和迷你模式下*/
            poster: config.background, /*视频的海报*/
            volume: 1, /*声音默认1*/
            muted: false, /*是否默认静音*/
            autoplay: true, /*否自动播放*/
            flip: true,/*是否视频翻转*/
            pip: true, /*是否显示画中画按钮*/
            autoSize: false,  /*自动调整播放器尺寸以隐藏黑边*/
            autoMini: true,  /*当播放器滚动到浏览器视口以外时，自动进入迷你播放模式*/
            //screenshot: true,  /*显示视频截图功能*/
            setting: true,  /*显示设置面板的开关按钮*/
            loop: false,  /*是否循环播放 默认: false*/
            lock: true,/*是否在移动端显示一个 锁定按钮 ，用于隐藏底部 控制栏*/
            playbackRate: true,  /*是否显示视频播放速度功能，会出现在 设置面板 和 右键菜单 里*/
            aspectRatio: true,  /*是否显示视频长宽比功能，会出现在 设置面板 和 右键菜单 里*/
            fullscreen: true,  /*是否在底部控制栏里显示播放器窗口全屏按钮*/
            //fullscreenWeb: true,  /*是否在底部控制栏里显示播放器网页全屏按钮*/
            miniProgressBar: true,  /*迷你进度条，只在播放器失去焦点后且正在播放时出现*/
            hotkey: true, /*是否启用快捷键*/
            backdrop: true,  
            fastForward: true,/*是否在移动端添加长按视频快进功能*/
            playsInline: true,  /*移动端是否使用 playsInline 模式*/
            autoOrientation: true,/*是否在移动端的网页全屏时，根据视频尺寸和视口尺寸，旋转播放器*/
            lang: navigator.language.toLowerCase(),
            whitelist: ['*'],
/*            contextmenu: [
                {
                    html: config.contextmenu,
                    click: function () {
                        window.open(config.contextlink);
                    },
                },
            ],
            moreVideoAttr: {
                crossOrigin: 'anonymous',
            },*/
            icons: {loading: '<img src="artplayer/img/ploading.gif">',state: '<img width="150" heigth="150" src="artplayer/img/state.svg">',indicator: '<img width="16" heigth="16" src="artplayer/img/indicator.svg">',},
            customType: {m3u8: playM3u8, flv: playFlv, ts: playTs, mpd: playMpd,},
            plugins: [
                artplayerPluginDanmuku({
                    danmuku: stray.danmuapi+config.url,
                    speed: 8, // 弹幕持续时间，单位秒，范围在[1 ~ 10]
                    opacity: 1, // 弹幕透明度，范围在[0 ~ 1]
                    fontSize: 24, // 字体大小，支持数字和百分比
                    color: '#FFFFFF', // 默认字体颜色
                    mode: 0, // 默认模式，0-滚动，1-静止
                    margin: [10, '75%'], // 弹幕上下边距，支持数字和百分比
                    antiOverlap: true, // 是否防重叠
                    useWorker: true, // 是否使用 web worker
                    synchronousPlayback: true, // 是否同步到播放速度
                    filter: (danmu) => danmu.text.length < 50, // 弹幕过滤函数
                    lockTime: config.sendtime, // 输入框锁定时间，单位秒，范围在[1 ~ 60]
                }),
            ]
        });
        let vide_init = $('.art-video-player');
        vide_init.prepend('<div class="vodlist-of danmu-hide"></div><div class="ec-listbox"><div class="anthology-wrap"></div></div></div><div class="r-button"><span class="yzmplayer-icon-content"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg"><path d="M448 128a106.667 106.667 0 0 1 106.667 106.667v576A106.667 106.667 0 0 1 448 917.333H128A106.667 106.667 0 0 1 21.333 810.667v-576A106.667 106.667 0 0 1 128 128h320z m448 256a106.667 106.667 0 0 1 106.667 106.667v320A106.667 106.667 0 0 1 896 917.333H661.333a42.667 42.667 0 1 1 0-85.333H896a21.333 21.333 0 0 0 21.333-21.333v-320A21.333 21.333 0 0 0 896 469.333H661.333a42.667 42.667 0 1 1 0-85.333zM448 213.333H128a21.333 21.333 0 0 0-21.333 21.334v554.666A21.333 21.333 0 0 0 128 810.667h320a21.333 21.333 0 0 0 21.333-21.334V234.667A21.333 21.333 0 0 0 448 213.333zM384 672a32 32 0 0 1 0 64H213.333a32 32 0 0 1 0-64z"></path></svg></span></div>');
        $(document).on('click', '.vodlist-of', function() {
            $(".ec-listbox").removeClass("ec-stting");
            $(this).hide();
        });
        stray.ad.controls.add({
            disable: false,
            name: 'danmu',
            index: 11,
            position: 'right',
            style:{
                "display":"none"
            },
            html: '<i class="art-icon art-icon-screenshot"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="22" height="22"><path d="M591.052962 516.456498h36.308951v30.253025h-36.308951z"></path><path d="M963.626469 391.364479h-73.188906c-17.155728-49.615001-43.482327-94.682429-76.567863-134.00604h94.240361v-82.312704H721.732234c-66.509786-43.469024-145.831366-68.940139-231.185337-68.940139-233.809093 0-423.356101 189.534727-423.3561 423.370427 0 233.835699 189.547007 423.358147 423.3561 423.358147 69.369927 0 134.605697-16.997116 192.382589-46.574758h225.179552v-82.286098H794.323529c37.269835-38.412868 67.288522-83.715657 87.898932-134.00604h81.402985v-82.284051h-57.233516c4.757351-25.366737 7.537673-51.462069 7.537674-78.206176 0-18.972097-1.688455-37.503149-4.105505-55.82647h53.800324v-82.286098z m-447.166389-88.653109c9.358135 14.814404 18.790972 33.631982 28.224832 56.451709l-54.448077 20.168342c-10.761088-20.168342-20.87033-38.960337-30.228465-56.451709l56.45171-20.168342zM407.561881 500.343519h-72.591295v48.393174h64.533782V689.864944c0 26.926256-6.055926 44.392046-18.167778 52.449559-10.784624 9.407254-38.308491 16.788361-82.674955 22.194488-4.027733-22.871917-11.434423-43.065841-22.143322-60.506049 48.342008 6.757914 69.186755-8.057513 62.455447-44.391023V603.160209h-60.479443V445.892373h66.559928v-54.4491h-84.676541V336.967568h147.184177v163.375951z m308.529889 185.518251H593.054549v82.650395h-62.48103v-82.650395H415.643953v-52.448536h114.929566v-34.281781h-100.816127V385.387347h143.178957c8.032954-24.197098 14.764262-45.689598 20.117177-64.533782 4.054339-13.410428 6.731308-20.818141 8.107655-22.194488 12.087293 2.728134 35.58138 10.81123 70.565149 24.222681 0 1.351787-2.026146 3.377934-6.055927 6.055926a319.803653 319.803653 0 0 0-38.309514 56.450687h62.50559v213.745128h-98.81454v34.281781h125.038807v52.44649z"></path><path d="M591.052962 439.83747h36.308951v28.224832h-36.308951zM492.262982 516.456498h38.310537v30.253025h-38.310537zM492.262982 439.83747h38.310537v28.224832h-38.310537z"></path></svg></i>',
            tooltip: '发布弹幕',
            click: function () {
                stray.DanMu.wap();
            },
        });
         $(document).on('click', '.r-button', function() {
            $(".art-video").toggleClass("gyro-contain");
        });
        
        $(".r-button").addClass("hp");
        $(".art-controls-center").addClass('dm-input-show');
        stray.load();
        stray.DanMu.initial();
        stray.ad.on('artplayerPluginDanmuku:emit', (danmu) => {
            stray.DanMu.add(danmu)
        });
    },
    'load': function() {
        stray.nextcass();
        stray.ad.on('video:loadedmetadata', () => {
            var currentTime = localStorage.getItem(config.vkey);
            stray.ad.seek = currentTime;
        });
        stray.ad.on('video:timeupdate', () => {
            var currentTime = Math.floor(stray.ad.currentTime);
            localStorage.setItem(config.vkey,currentTime);
        });
        stray.ad.on('video:pause', () => {
            if(config.zantingguanggaoqidong==1){
                stray.pause.play(config.zantingguanggaolianjie, config.zantingguanggaourl);
            }
        });
        stray.ad.on('video:play', () => {
            if(config.zantingguanggaoqidong==1){
                stray.pause.out();
            }
        });
        stray.ad.on('video:ended', () => {
            localStorage.removeItem(config.vkey);
            if(!!config.next){
                top.location.href=config.next;
            }
        });
    },
    "DanMu" :{
        "initial":function(){
            stray.ad.on('artplayerPluginDanmuku:error', (error) => {
                stray.Msg("弹幕加载失败",2000);
            });
            $(document).on('click', ".player-comment-setting-icon", function() {
                if (stray.ad.plugins.artplayerPluginDanmuku.isHide) {
                    stray.ad.plugins.artplayerPluginDanmuku.show();
                    $(this).addClass("danmu-setting-show").removeClass("danmu-setting-hide")

                } else {
                    stray.ad.plugins.artplayerPluginDanmuku.hide();
                    $(this).addClass("danmu-setting-hide").removeClass("danmu-setting-show")
                }
            });
        },
        "add" :function(d){
            if (d.text < 1) {
                stray.Msg("要输入内容啊~",2000);
                return;
            }
            if(config.pbgjz.length>0){
                for (var i = 0; i < config.pbgjz.length; i++) {
                    if (d.text.search(config.pbgjz[i]) != -1) {
                        stray.Msg("您发送的内容含有敏感字符，请规范您的弹幕内容",2000);
                        return;
                    }
                }
            }
            $.ajax({
                url: stray.danmuapisend,
                type: "post",
                dataType: "json",
                contentType: "application/x-www-form-urlencoded",
                //contentType: 'application/json',
                data:JSON.stringify({
                    "player": config.vkey,
                    "author": "",
                    "time":d.time,
                    "text":d.text,
                    "color":getrgb(d.color),
                    "type":d.mode == 1?'5':'0',
                    "size":"32px",
                    "referer":getreferrer()
                }),
                success: function (r) {
                    if(r['code'] != "23"){
                        stray.Msg(r['msg'],2000);
                    }
                },
                error:function (){
                    stray.Msg("弹幕入库失败",2000);
                }
            })
        },
        "wap":function(){
            $(".art-controls-right,.art-progress,.art-controls-left").hide();
            $(".art-controls-center").addClass("danmu-input-show");
            $(".danmu-hide").show();
            $(".danmu-hide").click(function(){
                $(".art-controls-center").removeClass("danmu-input-show");
                $(".art-controls-right,.art-progress,.art-controls-left").show();
            });
        }
    },
    'pause': {
        'play': function(l, p) {
            let pause_ad_html = '<div id="player_pause"><div class="tip" style="left:0;bottom:6px">广告</div><div class="tip g_close"><a href="javascript:" title="关闭广告" style="color:#f4f4f4">X</a></div><a href="' + l +
                    '" target="_blank"><img src="' + p + '"></a><script>$(".g_close").click(function(){$(this).parent().remove()})</script></div>';
            $('.art-video-player').prepend(pause_ad_html);
        },
        'out': function() {
            $('#player_pause').remove();
        }
    },
    'RemoveMsg':function(){
        $('.pop-msg').remove();
    },
    'Msg':function($msg,$timeout){
        $('.art-video-player').prepend('<div class="pop-msg"><div class="pop-content"></div></div>');
        $('.pop-msg .pop-close').click(function(){
            $('.pop-msg').remove();
        });
        $('.pop-msg .pop-content').html($msg);
        $('.pop-msg').show();
        setTimeout(stray.RemoveMsg,$timeout);
    },
    'videotype':function (url,type) {
        if(url.indexOf(".m3u8")>0){
            thetype="m3u8";
        }else if(url.indexOf(".flv")>0){
            thetype="flv";
        }else if(url.indexOf(".ts")>0){
            thetype="ts";
        }else if(url.indexOf(".mpd")>0){
            thetype="mpd";
        }else{
            if(type=="hls"||type=="m3u8"){
                thetype="m3u8";
            }else{
                thetype=type;
            }
        }
        return thetype;
    },
    'nextcass':function(){
        if(config.next){
            $('.art-control-playAndPause').after('<div class="art-control art-control-next" data-index="10"><i class="art-icon art-icon-next hint--rounded hint--top" aria-label="下一集" style="display: flex;"><svg xmlns="http://www.w3.org/2000/svg" height="22" width="22"><path d="M16 5a1 1 0 00-1 1v4.615a1.431 1.431 0 00-.615-.829L7.21 5.23A1.439 1.439 0 005 6.445v9.11a1.44 1.44 0 002.21 1.215l7.175-4.555a1.436 1.436 0 00.616-.828V16a1 1 0 002 0V6C17 5.448 16.552 5 16 5z"></path></svg></i></div>');
            $(".art-control-next").on("click", function() {
                top.location.href=config.next;
            });
        }
    },
}
function TheError(){
    $("body").append("<div id=\"error\"><h1>解析失败，请切换线路或刷新！</h1></div>");
    $("#loading").remove();
}
function getrgb(str){
  var pattern =new RegExp(/^#[0-9a-fA-F]{6}$/); 
  if(!pattern.test(str)){
    console.log("invalid hex");
    return;
  }
  var num = parseInt(str.slice(1),16);
  var b = num %　256;
  num = parseInt(num / 256);
  var g = num %　256;
  num = parseInt(num / 256);   
  var r = num %　256;
  return 'rgb('+r+","+g+","+b+")";
}
function getreferrer() {
    var ref = '';  
 　　if (document.referrer.length > 0) {  
 　　 　　ref = document.referrer;  
　　}else{
　　    ref = window.location.href; 
　　}  
    return ref;
}
/*播放地址是m3u8类型加载*/
function playM3u8(video, url, art) {
    if (Hls.isSupported()) {
        var config={
        maxBufferLength:120,
        }
        const hls = new Hls(config);
        hls.loadSource(url);
        hls.attachMedia(video);

        // optional
        art.hls = hls;
        art.once('url', () => hls.destroy());
        art.once('destroy', () => hls.destroy());
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = url;
    } else {
        art.notice.show = 'Unsupported playback format: m3u8';
    }
}
/*播放地址是flv类型加载*/
function playFlv(video, url, art) {
    if (flvjs.isSupported()) {
        const flv = flvjs.createPlayer({ type: 'flv', url });
        flv.attachMediaElement(video);
        flv.load();

        // optional
        art.flv = flv; 
        art.once('url', () => flv.destroy());
        art.once('destroy', () => flv.destroy());
    } else {
        art.notice.show = 'Unsupported playback format: flv';
    }
}
/*播放地址是ts类型加载*/
function playTs(video, url, art) {
    if (mpegts.isSupported()) {
        const ts = new mpegts.createPlayer({ type: 'mse', url });
        ts.attachMediaElement(video);
        ts.load();
        
        // optional
        art.ts = ts;
        art.once('url', () => ts.destroy());
        art.once('destroy', () => ts.destroy());
    } else {
        art.notice.show = 'Unsupported playback format: ts';
    }
}
/*播放地址是mpd类型加载*/
    function playMpd(video, url, art) {
    if (dashjs.supportsMediaSource()) {
        const dash = dashjs.MediaPlayer().create();
        dash.initialize(video, url, art.option.autoplay);

        // optional
        art.dash = dash; 
        art.once('url', () => dash.destroy());
        art.once('destroy', () => dash.destroy());
    } else {
        art.notice.show = 'Unsupported playback format: mpd';
    }
}
/*播放地址AES-CBC-128解密代码*/
function decrypt(text) {
        let decrypted = CryptoJS.AES.decrypt(text, CryptoJS.enc.Utf8.parse('ARTPLAYERliUlanG'),/*key16位*/ {
            iv: CryptoJS.enc.Utf8.parse('ArtplayerliUlanG'),/*iv16位*/
            mode: CryptoJS.mode.CBC,
            padding: CryptoJS.pad.Pkcs7
        });
        return decrypted.toString(CryptoJS.enc.Utf8)
}
//点到别的页面显示标题前戳
var OriginTitile = document.title;
var titleTime;
document.addEventListener("visibilitychange", function() {
if (document.hidden) {
document.title = "o(╥﹏╥)o你去哪了？快回来！- " + OriginTitile;
clearTimeout(titleTime);
}
else {
document.title = "๑乛◡乛๑亲爱的，欢迎回来~• - " + OriginTitile;
titleTime = setTimeout(function() {
document.title = OriginTitile;
}, 1500);
}
});