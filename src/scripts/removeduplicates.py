source_path = "/home/pi/access1.log.bak"
dest_path = "/home/pi/access2.log"

def remove_duplicate_lines(source_path):
	uniq = set()
	with open(source_path, "r") as fin:
		for line in fin:
			uniq.add(line)
	with open(dest_path, "w") as fout:
		for line in uniq:
			fout.write(line)

remove_duplicate_lines(source_path) 			
