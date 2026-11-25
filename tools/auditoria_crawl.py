import re,sys,json,urllib.request,html,time
B='https://rocaingenieria.com.ar'
def get(u):
    r=urllib.request.Request(u,headers={'User-Agent':'Mozilla/5.0 seo-audit'})
    try:
        with urllib.request.urlopen(r,timeout=60) as f: return f.status,f.read().decode('utf-8','replace'),dict(f.headers)
    except urllib.error.HTTPError as e: return e.code,e.read().decode('utf-8','replace'),dict(e.headers)
sm=[]
_,x,_=get(B+'/sitemap_index.xml')
for s in re.findall(r'<loc>([^<]+)',x):
    _,y,_=get(s); sm+= [u for u in re.findall(r'<loc>([^<]+)',y) if not re.search(r'\.(jpe?g|png|webp|avif|gif)$',u)]
sm=sorted(set(sm))
out=[]
for u in sm:
    t=time.time(); c,h,hd=get(u); dt=time.time()-t
    g=lambda p: (re.search(p,h,re.S|re.I) or [None,None])[1]
    d=dict(url=u,code=c,t=round(dt,2),title=html.unescape(g(r'<title>(.*?)</title>') or ''),
      desc=html.unescape(g(r'<meta name="description" content="([^"]*)"') or ''),
      canon=g(r'<link rel="canonical" href="([^"]*)"'),robots=g(r'<meta name=.robots. content="([^"]*)"'),
      h1=len(re.findall(r'<h1',h,re.I)),ldtypes=re.findall(r'"@type":"([A-Za-z]+)"',h),
      imgs_noalt=len(re.findall(r'<img(?![^>]*\balt="[^"]+")[^>]*>',h)),lang=g(r'<html[^>]*lang="([^"]*)"'),
      oglocale=g(r'og:locale" content="([^"]*)"'),ogimg=g(r'og:image" content="([^"]*)"'),
      granalla_link=('/granalla-de-acero/' in h),size=len(h))
    out.append(d)
json.dump(out,open(sys.argv[1],'w'),ensure_ascii=False,indent=1)
print(len(out))
