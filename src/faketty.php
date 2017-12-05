# Module API

def apply_faketty(code, faketty=False):
    if faketty:
        code = "python -m run.faketty /bin/bash -c %s" % shlex.quote(code)
    return code


# Main program

if __name__ == '__main__':

    import sys
    import pexpect

    child = pexpect.spawn(sys.argv[1], sys.argv[2:])
    child.logfile_read = sys.stdout.buffer
    child.expect(pexpect.EOF)
    child.close()
    exit(child.exitstatus)
