	var stray = {
		'start': function() {
			$.ajaxSettings.timeout="30000"; 
			$.ajaxSettings.async = true;
			$.post("api.php", {"url":config.url,"time":config.time,"key":config.key},
			function(data) {
				if(data.code=="200"){
					stray.play(data.url);
				}else{
					stray.TheError();
				}
			},'json').error(function (xhr, status, info) {
				stray.TheError();
			});
		},
		'play': function(url) {
			$("#loading").remove();
			$("body").append("<div id=\"player\" style=\"position:absolute;left:0px;top:0px;\"></div>");
			if(config.danmuqidong==1){
				stray.danmuapi=config.api + '&url=';
				stray.danmuapilist=config.api + config.url;
				stray.player.dmplay(url);
			}else{
				stray.player.dplay(url);
			}
			if (document.pictureInPictureEnabled == true) {
				if (document.getElementById('enterhzh') != null) {
					document.getElementById('enterhzh').addEventListener('click', ()=>{
						straydmvideo.requestPictureInPicture().catch(error=>{
							console.log(error);
						}
						);
					}
					);
					document.getElementById('enterhzh').id = "exithzh";
				}
				if (document.getElementById('exithzh') != null) {
					document.getElementById('exithzh').addEventListener('click', ()=>{
						document.exitPictureInPicture().catch(error=>{
							console.log(error);
						}
						);
					}
					);
					document.getElementById('exithzh').id = "enterhzh";
				}
			}
			var qjcolor = '<style type="text/css">.showdan-setting .straydmplayer-toggle input+label{border: 1px solid ' + config.themeColor + '!important;background: ' + config.themeColor + '!important;}.straydmplayer-controller .straydmplayer-icons .straydmplayer-setting .straydmplayer-setting-speed-item:hover .straydmplayer-label{color: ' + config.themeColor + ';}.straydmplayer-controller .straydmplayer-icons .straydmplayer-toggle input+label{background: ' + config.themeColor + ';}.straydmplayer .straydmplayer-controller .straydmplayer-icons.straydmplayer-comment-box .straydm-straydmplayer-send-icon{background-color: ' + config.themeColor + ';}.straydmplayer .straydmplayer-controller .straydmplayer-icons.straydmplayer-comment-box .straydm-straydmplayer-send-icon:active{background-color:' + config.themeColor + ';}.straydmplayer-setting-speeds:hover .title{background-color:' + config.themeColor + '!important;}</style>';
			$('head').append(qjcolor);
			$(function() {
				$(".straydmplayer-setting-speeds,.straydmplayer-setting-speed-item").on("click", function() {
					$(".speed-stting").toggleClass("speed-stting-open");
				});
				$(".speed-stting.straydmplayer-setting-speed-item").click(function() {
					$(".straydmplayer-setting-speeds.title").text($(this).text());
				});
			});
			$(".straydmplayer-fulloff-icon").on("click", function() {
				stray.dp.fullScreen.cancel();
			});
			$(".straydmplayer-showing").on("click", function() {
				stray.dp.play();
			});
			$('.straydmplayer-notice').remove();
		},
		'player': {
			'dplay': function(url) {
				$('body').addClass("danmu-off");
				stray.dp = new straydmplayer({
					autoplay: true,
					element: document.getElementById('player'),
					theme: config.themeColor,
					video: {
						url: decrypt(url),
						pic: config.background,
						type: 'auto',
						customType: {
							customHls: function(video, player) {
								const hls = new Hls({
									'debug': false,
									'p2pConfig': {
										'logLevel': true,
										'live': false
									}
								});
								hls.loadSource(video.src);
								hls.attachMedia(video);
								hls.p2pEngine.on('stats', function(data) {
									tota1P2PDownloaded = data["totalP2PDownloaded"];
									totalP2PUploaded = data["totalP2PUploaded"];
									updateStats();
								}).on("peerId", function(peerIdData) {
									_peerId = peerIdData;
								}).on("peers", function(peersData) {
									_peers = peersData.length;
									updateStats();
								});
							}
						}
					},
				});
				stray.def();
			},
			'dmplay': function(url) {
				stray.dp = new straydmplayer({
					autoplay: true,
					element: document.getElementById('player'),
					theme: config.themeColor,
					logo: '',
					video: {
						url: decrypt(url),
						pic: config.background,
						type: 'auto',
						customType: {
							customHls: function(video, player) {
								const hls = new Hls({
									'debug': false,
									'p2pConfig': {
										'logLevel': true,
										'live': false
									}
								});
								hls.loadSource(video.src);
								hls.attachMedia(video);
								hls.p2pEngine.on('stats', function(data) {
									tota1P2PDownloaded = data["totalP2PDownloaded"];
									totalP2PUploaded = data["totalP2PUploaded"];
									updateStats();
								}).on("peerId", function(peerIdData) {
									_peerId = peerIdData;
								}).on("peers", function(peersData) {
									_peers = peersData.length;
									updateStats();
								});
							}
						}
					},
					danmaku: {
						id: config.vkey,
						api: stray.danmuapi,
						user: ''
					}
				});
				stray.load();
			},
		},
		'load': function() {
			stray.danmu.send();
			stray.danmu.list();
			stray.def();
			stray.dp.danmaku.opacity(1);
		},
		'def': function() {
			stray.stime = 0;
			stray.headt = straydmck.get("headt");
			stray.lastt = straydmck.get("lastt");
			stray.last_tip = parseInt(stray.lastt) + 10;
			stray.frists = straydmck.get('frists');
			stray.lasts = straydmck.get('lasts');
			stray.playtime = Number(localStorage.getItem(config.vkey));
			stray.ctime = stray.formatTime(stray.playtime);
			stray.dp.on("loadedmetadata", function() {
				stray.dp.seek(stray.playtime);
			});
			stray.dp.on('timeupdate', function(e) {
				var currentTime = Math.floor(stray.dp.video.currentTime);
				localStorage.setItem(config.vkey,currentTime);
			});
			stray.dp.on('pause', function() {
				stray.MYad.pause.play(config.zantingguanggaolianjie, config.zantingguanggaourl);
			});
			stray.dp.on('play', function() {
				stray.MYad.pause.out();
			});
			stray.dp.on("ended", function() {
				localStorage.removeItem(config.vkey);
				if (config.next) {
					top.location.href=config.next;
				} else {
					stray.dp.notice("视频播放已结束");
				}
			});
			stray.jump.def();
			stray.jump.head();
		},
		'jump': {
			'def': function() {
				h = ".straydmplayer-setting-jfrist label";
				l = ".straydmplayer-setting-jlast label";
				f = "#fristtime";
				j = "#jumptime";
				a(h, 'frists', stray.frists, 'headt', stray.headt, f);
				a(l, 'lasts', stray.lasts, 'lastt', stray.lastt, j);
				function er() {
					layer.msg("请输入有效时间哟！");
				}
				function su() {
					layer.msg("设置完成，将在刷新或下一集生效");
				}
				function a(b, c, d, e, g, t) {
					$(b).on("click", function() {
						o = $(t).val();
						if (o > 0) {
							$(b).toggleClass('checked');
							su();
							g = $(t).val();
							straydmck.set(e, g);
						} else {
							er();
						};
					});
					if (d == 1) {
						$(b).addClass('checked');
						$(b).click(function() {
							o = $(t).val();
							if (o > 0) {
								straydmck.set(c, 0);
							} else {
								er();
							};
						});
					} else {
						$(b).click(function() {
							o = $(t).val();
							if (o > 0) {
								straydmck.set(c, 1);
							} else {
								er();
							};
						});
					}
				};
				$(f).attr({
					"value": stray.headt
				});
				$(j).attr({
					"value": stray.lastt
				});
				stray.jump.last();
			},
			'head': function() {
				if (stray.stime > stray.playtime)
					stray.playtime = stray.stime;
				if (stray.frists == 1) {
					if (stray.headt > stray.playtime || stray.playtime == 0) {
						stray.jump_f = 1
					} else {
						stray.jump_f = 0
					}
				}
				if (stray.jump_f == 1) {
					stray.dp.seek(stray.headt);
					stray.dp.notice("已为您跳过片头");
				}
			},
			'last': function() {
				if (config.next != '') {
					if (stray.lasts == 1) {
						setInterval(function() {
							var e = stray.dp.video.duration - stray.dp.video.currentTime;
							if (e < stray.last_tip)
								stray.dp.notice('即将为您跳过片尾');
							if (stray.lastt > 0 && e < stray.lastt) {
								localStorage.removeItem(config.vkey,  "");
								top.location.href=config.next;
							};
						}, 1000);
					};
				} else {
					$(".icon-xj").remove();
				};
			},
			'ad': function(a, b) {}
		},
		'danmu': {
			'send': function() {
				g = $(".straydm-straydmplayer-send-icon");
				d = $("#dmtext");
				h = ".straydmplayer-comment-setting-";
				$(h + "color input").on("click", function() {
					r = $(this).attr("value");
					setTimeout(function() {
						d.css({
							"color": r
						});
					}, 100);
				});
				$(h + "type input").on("click", function() {
					t = $(this).attr("value");
					setTimeout(function() {
						d.attr("dmtype", t);
					}, 100);
				});
				$(h + "font input").on("click", function() {
					t = $(this).attr("value");
					setTimeout(function() {
						d.attr("size", t);
					}, 100);
				});
				g.on("click", function() {
					a = document.getElementById("dmtext");
					a = a.value;
					b = d.attr("dmtype");
					c = d.css("color");
					z = d.attr("size");
					if(config.pbgjz.length>0){
						for (var i = 0; i < config.pbgjz.length; i++) {
							if (a.search(config.pbgjz[i]) != -1) {
								layer.msg("您发送的内容含有敏感字符，请规范您的弹幕内容");
								return;
							}
						}
					}
					if (a.length < 1) {
						layer.msg("要输入内容啊~");
						return;
					}
					var e = Date.parse(new Date());
					var f = straydmck.get('dmsent', e);
					if (e - f < config.sendtime * 1000) {
						layer.msg('请勿频繁操作！发送弹幕需间隔' + config.sendtime + '秒~');
						return;
					}
					d.val("");
					stray.dp.danmaku.send({
						text: a,
						color: c,
						type: b,
						size: z
					});
					straydmck.set('dmsent', e);
				});
				function k() {
					g.trigger("click");
				};
				d.keydown(function(e) {
					if (e.keyCode == 13) {
						k();
					};
				});
			},
			'list': function() {
				$(".straydmplayer-list-icon,.straydm-straydmplayer-send-icon").on("click", function() {
					$(".list-show").empty();
					$.ajax({
						url: stray.danmuapilist+config.url,
						success: function(d) {
							if (d.code == 23) {
								a = d.danmuku;
								b = d.name;
								c = d.danum;
								$(".danmuku-num").text(c);
								$(a).each(function(index, item) {
									l = `<d class="danmuku-list" time="${item[0]}"><li>${stray.formatTime(item[0])}</li><li title="${item[4]}">${item[4]}</li><li title="用户：${item[3]}  IP地址：${item[5]}">${item[6]}</li><li class="report" onclick="stray.danmu.report(\'${item[5]}\',\'${b}\',\'${item[4]}\',\'${item[3]}\')">举报</li></d>`
									$(".list-show").append(l);
								})
							}
							$(".danmuku-list").on("dblclick", function() {
								stray.dp.seek($(this).attr("time"))
							})
						}
					});
				});
				$("#vod-title").append("<e>" + config.title + "</e>");
				add('.straydmplayer-list-icon', ".straydmplayer-danmu", 'show');
				function add(div1, div2, div3, div4) {
					$(div1).click(function() {
						$(div2).toggleClass(div3);
						$(div4).remove();
					});
				}
			},
			'report': function(a, b, c, d) {
				layer.confirm('' + c + '<!--br><br><span style="color:#333">请选择需要举报的类型</span-->', {
					anim: 1,
					title: '举报弹幕',
					btn: ['违法违禁', '色情低俗', '恶意刷屏', '赌博诈骗', '人身攻击', '侵犯隐私', '垃圾广告', '剧透', '引战'],
					btn3: function(index, layero) {
						stray.danmu.post_r(a, b, c, d, '恶意刷屏');
					},
					btn4: function(index, layero) {
						stray.danmu.post_r(a, b, c, d, '赌博诈骗');
					},
					btn5: function(index, layero) {
						stray.danmu.post_r(a, b, c, d, '人身攻击');
					},
					btn6: function(index, layero) {
						stray.danmu.post_r(a, b, c, d, '侵犯隐私');
					},
					btn7: function(index, layero) {
						stray.danmu.post_r(a, b, c, d, '垃圾广告');
					},
					btn8: function(index, layero) {
						stray.danmu.post_r(a, b, c, d, '剧透');
					},
					btn9: function(index, layero) {
						stray.danmu.post_r(a, b, c, d, '引战');
					}
				}, function(index, layero) {
					stray.danmu.post_r(a, b, c, d, '违法违禁');
				}, function(index) {
					stray.danmu.post_r(a, b, c, d, '色情低俗');
				});
			},
			'post_r': function(a, b, c, d, type) {
				$.ajax({
					type: "GET",
					url: config.api + '?ac=report&cid=' + d + '&user=' + a + '&type=' + type + '&title=' + b + '&text=' + c + '&referer=' + document.referrer,
					cache: false,
					dataType: 'json',
					beforeSend: function() {},
					success: function(data) {
						layer.msg("举报成功！感谢您为守护弹幕作出了贡献");
					},
					error: function(data) {
						var msg = "服务故障 or 网络异常，稍后再试6！";
						layer.msg(msg);
					}
				});
			}
		},
		'formatTime': function(seconds) {
			return [parseInt(seconds / 60 / 60), parseInt(seconds / 60 % 60), parseInt(seconds % 60)].join(":").replace(/\b(\d)\b/g, "0$1");
		},
		'MYad': {
			'pause': {
				'play': function(l, p) {
					if (config.zantingguanggaoqidong == 1) {
						var pause_ad_html = '<div id="player_pause"><div class="adimg"><a style="color:#ffffff;">广告</a></div><div class="tip"><a style="color:#ffffff;cursor:pointer;" onclick="javascript:turnoff(' + "'player_pause'" + ')" title="点击关闭广告">✖</a></div><a href="' + l + '" target="_blank" ><img src="' + p + '"></a></div>';
						$('#player').before(pause_ad_html);
					}
				},
				'out': function() {
					if (config.zantingguanggaoqidong == 1) {
						$('#player_pause').remove();
					}
				}
			}
		},
		'next':function(){
			top.location.href=config.next;
		},
		'TheError':function(){
			$("body").append("<div id=\"error\"><h1>解析失败，请切换线路或刷新！</h1></div>");
			$("#loading").remove();
		},
	}
	function turnoff(obj) {
		document.getElementById(obj).style.display = "none";
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