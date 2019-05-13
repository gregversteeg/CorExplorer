is.installed <- function(mypkg){
    is.element(mypkg, installed.packages()[,1])
} 

if (is.installed("biomaRt")) {
	cat("biomaRt is installed and accessible<p>\n")
} else {
	cat("ERROR:biomaRt is either not installed or not accessible to webserver<p>\n")
}

if (is.installed("survival")) {
	cat("survival is installed and accessible<p>\n")
} else {
	cat("ERROR:survival is either not installed or not accessible to webserver<p>\n")
}
